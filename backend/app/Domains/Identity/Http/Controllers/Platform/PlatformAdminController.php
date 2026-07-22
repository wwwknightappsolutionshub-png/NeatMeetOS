<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\PlatformInvoice;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\PlatformAdminService;
use App\Domains\Identity\Services\PlatformBillingService;
use App\Domains\Identity\Services\TenantTierService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    public function __construct(
        private readonly PlatformAdminService $platform,
        private readonly TenantTierService $tiers,
        private readonly PlatformBillingService $billing,
    ) {}

    public function overview(): JsonResponse
    {
        return ApiResponse::success($this->platform->overview());
    }

    public function tenants(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        return ApiResponse::success(
            $this->platform->listTenants($data['search'] ?? null, $data['status'] ?? null),
        );
    }

    public function unlockTenantTier(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $data = $request->validate([
            'activate_plan_slug' => ['nullable', 'string', 'in:basic,pro,diamond'],
        ]);

        $subscription = $this->tiers->unlockTier(
            $tenant,
            $data['activate_plan_slug'] ?? null,
        );

        return ApiResponse::success([
            'tenant_id' => $tenant->id,
            'tier_unlocked' => (bool) $subscription->tier_unlocked,
            'tier_unlocked_at' => $subscription->tier_unlocked_at?->toIso8601String(),
            'plan_slug' => $subscription->plan?->slug,
            'desired_plan_slug' => $subscription->desired_plan_slug,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ], 'Tiers unlocked');
    }

    public function suspendTenant(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $this->platform->suspendTenant($tenant, $data['reason'] ?? null);

        return ApiResponse::success([
            'id' => $tenant->id,
            'status' => $tenant->status,
            'suspended_at' => $tenant->suspended_at?->toIso8601String(),
            'suspension_reason' => $tenant->suspension_reason,
        ], 'Tenant suspended');
    }

    public function unsuspendTenant(string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $tenant = $this->platform->unsuspendTenant($tenant);

        return ApiResponse::success([
            'id' => $tenant->id,
            'status' => $tenant->status,
            'suspended_at' => null,
            'suspension_reason' => null,
        ], 'Tenant unsuspended');
    }

    public function impersonateTenant(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $data = $request->validate([
            'user_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->platform->impersonateTenant(
            $tenant,
            $request->user(),
            $data['user_id'] ?? null,
        );

        return ApiResponse::success($result, 'Impersonation token issued');
    }

    public function invoices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $items = $this->billing->listInvoices(
            $data['tenant_id'] ?? null,
            $data['status'] ?? null,
        )->map(fn (PlatformInvoice $invoice) => $this->invoicePayload($invoice));

        return ApiResponse::success($items->all());
    }

    public function showInvoice(string $id): JsonResponse
    {
        return ApiResponse::success($this->invoicePayload($this->billing->findInvoice($id)));
    }

    public function markInvoicePaid(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $this->billing->markPaid(
            $this->billing->findInvoice($id),
            $data['provider_reference'] ?? null,
        );

        return ApiResponse::success($this->invoicePayload($invoice), 'Invoice marked paid');
    }

    public function failInvoicePayment(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = $this->billing->recordFailedPayment(
            $this->billing->findInvoice($id),
            $data['reason'] ?? null,
        );

        return ApiResponse::success($this->invoicePayload($invoice), 'Payment failure recorded');
    }

    public function processBilling(Request $request): JsonResponse
    {
        $data = $request->validate([
            'generate' => ['nullable', 'boolean'],
            'collect' => ['nullable', 'boolean'],
        ]);

        $result = [];
        if (($data['generate'] ?? true) === true) {
            $result['generate'] = $this->billing->generateDueInvoices();
        }
        if (($data['collect'] ?? true) === true) {
            $result['collect'] = $this->billing->processDuePaymentAttempts();
        }

        return ApiResponse::success($result, 'Billing processed');
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(PlatformInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'tenant_name' => $invoice->tenant?->name,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'amount_cents' => $invoice->amount_cents,
            'amount_paid_cents' => $invoice->amount_paid_cents,
            'billing_interval' => $invoice->billing_interval,
            'period_start' => $invoice->period_start?->toIso8601String(),
            'period_end' => $invoice->period_end?->toIso8601String(),
            'due_at' => $invoice->due_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'attempt_count' => $invoice->attempt_count,
            'failure_reason' => $invoice->failure_reason,
            'plan_slug' => $invoice->plan?->slug,
            'attempts' => $invoice->relationLoaded('attempts')
                ? $invoice->attempts->map(fn ($a) => [
                    'id' => $a->id,
                    'status' => $a->status,
                    'provider' => $a->provider,
                    'provider_reference' => $a->provider_reference,
                    'failure_message' => $a->failure_message,
                    'attempted_at' => $a->attempted_at?->toIso8601String(),
                ])->all()
                : null,
        ];
    }
}
