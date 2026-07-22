<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformInvoice;
use App\Domains\Identity\Models\PlatformInvoiceAttempt;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SaaS tenant billing: invoice generation, payment attempts, past-due / suspend handling.
 */
class PlatformBillingService
{
    public const MAX_FAILED_ATTEMPTS_BEFORE_SUSPEND = 3;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PlatformAdminService $platformAdmin,
    ) {}

    /**
     * @return Collection<int, PlatformInvoice>
     */
    public function listInvoices(?string $tenantId = null, ?string $status = null, int $limit = 100): Collection
    {
        $query = PlatformInvoice::query()->with(['tenant:id,name,slug', 'plan:id,name,slug'])->orderByDesc('due_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->limit($limit)->get();
    }

    public function findInvoice(string $id): PlatformInvoice
    {
        return PlatformInvoice::query()->with(['tenant', 'plan', 'attempts'])->findOrFail($id);
    }

    /**
     * Generate open invoices for active subscriptions whose period has ended.
     *
     * @return array{generated: int, skipped: int}
     */
    public function generateDueInvoices(): array
    {
        $generated = 0;
        $skipped = 0;

        $subscriptions = TenantSubscription::withoutGlobalScopes()
            ->with(['plan', 'tenant'])
            ->whereIn('status', [
                TenantSubscription::STATUS_ACTIVE,
                TenantSubscription::STATUS_TRIAL,
                TenantSubscription::STATUS_PAST_DUE,
            ])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->tenant === null || $subscription->plan === null) {
                $skipped++;

                continue;
            }

            if ($subscription->status === TenantSubscription::STATUS_TRIAL
                && $subscription->trial_ends_at !== null
                && $subscription->trial_ends_at->isFuture()) {
                $skipped++;

                continue;
            }

            $exists = PlatformInvoice::query()
                ->where('tenant_subscription_id', $subscription->id)
                ->where('period_start', $subscription->current_period_start)
                ->where('period_end', $subscription->current_period_end)
                ->whereIn('status', [
                    PlatformInvoice::STATUS_OPEN,
                    PlatformInvoice::STATUS_PAST_DUE,
                    PlatformInvoice::STATUS_PAID,
                ])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $this->createInvoiceForSubscription($subscription);
            $generated++;
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    public function createInvoiceForSubscription(TenantSubscription $subscription): PlatformInvoice
    {
        $plan = $subscription->plan ?? SubscriptionPlan::query()->findOrFail($subscription->subscription_plan_id);
        $amount = (int) ($plan->display_price_cents ?? 0);
        $periodStart = $subscription->current_period_start ?? now()->startOfMonth();
        $periodEnd = $subscription->current_period_end ?? now()->endOfMonth();

        return DB::transaction(function () use ($subscription, $plan, $amount, $periodStart, $periodEnd) {
            $invoice = PlatformInvoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'tenant_subscription_id' => $subscription->id,
                'subscription_plan_id' => $plan->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'status' => PlatformInvoice::STATUS_OPEN,
                'currency' => 'GBP',
                'amount_cents' => $amount,
                'amount_paid_cents' => 0,
                'billing_interval' => $subscription->billing_interval ?? $plan->billing_interval,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_at' => now()->addDays(7),
                'next_attempt_at' => now(),
                'line_items_json' => [[
                    'description' => $plan->name.' ('.$plan->billing_interval.')',
                    'amount_cents' => $amount,
                ]],
                'metadata_json' => [
                    'plan_slug' => $plan->slug,
                ],
            ]);

            $this->auditLogger->log('platform_invoice.created', $invoice, null, [
                'tenant_id' => $invoice->tenant_id,
                'amount_cents' => $invoice->amount_cents,
            ]);

            return $invoice;
        });
    }

    /**
     * Attempt collection for open/past_due invoices due for retry.
     *
     * @return array{processed: int, paid: int, failed: int}
     */
    public function processDuePaymentAttempts(): array
    {
        $invoices = PlatformInvoice::query()
            ->whereIn('status', [PlatformInvoice::STATUS_OPEN, PlatformInvoice::STATUS_PAST_DUE])
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('due_at')
            ->limit(100)
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $result = $this->attemptPayment($invoice);
            if ($result['status'] === 'paid') {
                $paid++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $invoices->count(),
            'paid' => $paid,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{status: string, invoice: PlatformInvoice}
     */
    public function attemptPayment(PlatformInvoice $invoice, bool $forceSuccess = false, bool $forceFailure = false): array
    {
        return DB::transaction(function () use ($invoice, $forceSuccess, $forceFailure) {
            $invoice->refresh();
            if (in_array($invoice->status, [PlatformInvoice::STATUS_PAID, PlatformInvoice::STATUS_VOID], true)) {
                return ['status' => $invoice->status, 'invoice' => $invoice];
            }

            $subscription = TenantSubscription::withoutGlobalScopes()->find($invoice->tenant_subscription_id);
            $hasCustomer = $subscription?->billing_customer_id !== null && $subscription->billing_customer_id !== '';
            $simulateFailure = $forceFailure || (($invoice->metadata_json['simulate_failure'] ?? false) === true);

            // Free invoices auto-settle. Paid invoices collect when a billing customer is on file
            // (Stripe charge extension point) or when forceSuccess is used by support tooling.
            $success = $forceSuccess
                || (! $simulateFailure && ($invoice->amount_cents === 0 || $hasCustomer));

            $attempt = PlatformInvoiceAttempt::query()->create([
                'platform_invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'status' => $success ? 'succeeded' : 'failed',
                'provider' => $subscription?->provider ?? 'manual',
                'provider_reference' => $success ? 'pinv_'.Str::lower(Str::random(12)) : null,
                'failure_code' => $success ? null : 'payment_failed',
                'failure_message' => $success ? null : 'Payment collection failed.',
                'response_json' => [
                    'has_billing_customer' => $hasCustomer,
                    'amount_cents' => $invoice->amount_cents,
                ],
                'attempted_at' => now(),
            ]);

            $invoice->attempt_count = (int) $invoice->attempt_count + 1;

            if ($success) {
                $invoice->status = PlatformInvoice::STATUS_PAID;
                $invoice->amount_paid_cents = $invoice->amount_cents;
                $invoice->paid_at = now();
                $invoice->failure_reason = null;
                $invoice->next_attempt_at = null;
                $invoice->save();

                if ($subscription !== null) {
                    $subscription->status = TenantSubscription::STATUS_ACTIVE;
                    $subscription->current_period_start = $invoice->period_end ?? now();
                    $interval = $subscription->billing_interval ?? 'monthly';
                    $subscription->current_period_end = $interval === 'annual'
                        ? ($subscription->current_period_start?->copy()->addYear() ?? now()->addYear())
                        : ($subscription->current_period_start?->copy()->addMonth() ?? now()->addMonth());
                    $subscription->save();
                }

                $this->auditLogger->log('platform_invoice.paid', $invoice, null, [
                    'attempt_id' => $attempt->id,
                ]);

                return ['status' => 'paid', 'invoice' => $invoice->fresh('attempts')];
            }

            $invoice->status = PlatformInvoice::STATUS_PAST_DUE;
            $invoice->failure_reason = $attempt->failure_message;
            $invoice->next_attempt_at = now()->addDays(min(7, (int) $invoice->attempt_count));
            $invoice->save();

            if ($subscription !== null) {
                $subscription->status = TenantSubscription::STATUS_PAST_DUE;
                $subscription->save();
            }

            $this->auditLogger->log('platform_invoice.payment_failed', $invoice, null, [
                'attempt_id' => $attempt->id,
                'attempt_count' => $invoice->attempt_count,
            ]);

            if ($invoice->attempt_count >= self::MAX_FAILED_ATTEMPTS_BEFORE_SUSPEND) {
                $this->handleRepeatedFailure($invoice, $subscription);
            }

            return ['status' => 'failed', 'invoice' => $invoice->fresh('attempts')];
        });
    }

    public function markPaid(PlatformInvoice $invoice, ?string $providerReference = null): PlatformInvoice
    {
        $invoice->metadata_json = array_merge($invoice->metadata_json ?? [], [
            'manual_paid_reference' => $providerReference,
        ]);
        $invoice->save();

        return $this->attemptPayment($invoice, forceSuccess: true)['invoice'];
    }

    public function recordFailedPayment(PlatformInvoice $invoice, ?string $reason = null): PlatformInvoice
    {
        $invoice->metadata_json = array_merge($invoice->metadata_json ?? [], [
            'simulate_failure' => true,
            'external_failure_reason' => $reason,
        ]);
        $invoice->save();

        return $this->attemptPayment($invoice, forceFailure: true)['invoice'];
    }

    private function handleRepeatedFailure(PlatformInvoice $invoice, ?TenantSubscription $subscription): void
    {
        if ($subscription !== null) {
            $subscription->status = TenantSubscription::STATUS_SUSPENDED;
            $subscription->save();
        }

        $tenant = Tenant::query()->find($invoice->tenant_id);
        if ($tenant !== null && $tenant->status !== 'suspended') {
            $this->platformAdmin->suspendTenant($tenant, 'Repeated platform invoice payment failures.');
        }

        $invoice->status = PlatformInvoice::STATUS_UNCOLLECTIBLE;
        $invoice->next_attempt_at = null;
        $invoice->save();

        $this->auditLogger->log('platform_invoice.uncollectible', $invoice, null, [
            'attempt_count' => $invoice->attempt_count,
        ]);
    }

    private function nextInvoiceNumber(): string
    {
        return 'NMI-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
