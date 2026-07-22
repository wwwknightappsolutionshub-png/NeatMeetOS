<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Enums\WalletEntryDirection;
use App\Domains\Memberships\Enums\WalletEntryType;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Shared\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientMembershipService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly WalletLedgerService $walletLedger,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly AuditLogger $auditLogger,
        private readonly MarketingAutomationTriggerService $marketingTriggers,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = ClientMembership::query()
            ->with(['client', 'membershipPlan'])
            ->orderByDesc('started_at');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): ClientMembership
    {
        return $this->scope->findClientMembership($id);
    }

    public function assign(array $data, ?string $teamMemberId = null): ClientMembership
    {
        $client = $this->scope->findClient($data['client_id']);
        $plan = $this->scope->findPlan($data['membership_plan_id']);

        if ($plan->status !== MembershipPlanStatus::ACTIVE) {
            throw ValidationException::withMessages(['membership_plan_id' => ['Plan is not active.']]);
        }

        $startedAt = isset($data['started_at']) ? Carbon::parse($data['started_at']) : now();
        $periodEnd = $this->computePeriodEnd($startedAt, $plan->billing_frequency);

        $membership = DB::transaction(function () use ($data, $client, $plan, $startedAt, $periodEnd, $teamMemberId) {
            $membership = ClientMembership::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_id' => $client->id,
                'membership_plan_id' => $plan->id,
                'status' => $data['status'] ?? ClientMembershipStatus::ACTIVE,
                'source' => $data['source'] ?? 'admin',
                'started_at' => $startedAt,
                'trial_ends_at' => isset($data['trial_ends_at']) ? Carbon::parse($data['trial_ends_at']) : null,
                'current_period_starts_at' => $startedAt,
                'current_period_ends_at' => $periodEnd,
                'next_billing_date' => $periodEnd->toDateString(),
                'billing_anchor_date' => $startedAt->toDateString(),
                'cancel_at_period_end' => false,
                'price_cents_snapshot' => $plan->price_cents,
                'joining_fee_cents_snapshot' => $plan->joining_fee_cents,
                'included_wallet_credit_cents_snapshot' => $plan->included_wallet_credit_cents,
                'included_loyalty_points_snapshot' => $plan->included_loyalty_points,
                'included_entitlement_quantity_snapshot' => $plan->included_entitlement_quantity,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($plan->included_wallet_credit_cents > 0) {
                $this->walletLedger->postEntry([
                    'client_id' => $client->id,
                    'entry_type' => WalletEntryType::MEMBERSHIP_CREDIT,
                    'direction' => WalletEntryDirection::CREDIT,
                    'amount_cents' => $plan->included_wallet_credit_cents,
                    'notes' => "Membership benefit: {$plan->name}",
                    'source_type' => ClientMembership::class,
                    'source_id' => $membership->id,
                ], $teamMemberId);
            }

            if ($plan->included_loyalty_points > 0) {
                $this->loyaltyLedger->postEntry([
                    'client_id' => $client->id,
                    'entry_type' => LoyaltyEntryType::MEMBERSHIP_BONUS,
                    'direction' => LoyaltyEntryDirection::CREDIT,
                    'points' => $plan->included_loyalty_points,
                    'notes' => "Membership bonus: {$plan->name}",
                    'source_type' => ClientMembership::class,
                    'source_id' => $membership->id,
                ], $teamMemberId);
            }

            return $membership;
        });

        $this->auditLogger->log('client_membership.created', $membership, null, $membership->only(['client_id', 'membership_plan_id', 'status']));

        try {
            $this->marketingTriggers->fireMembershipStarted($membership);
        } catch (\Throwable) {
            // Marketing automations must not block membership assignment.
        }

        return $membership->fresh()->load(['client', 'membershipPlan']);
    }

    public function update(ClientMembership $membership, array $data): ClientMembership
    {
        $this->scope->assertTenantModel($membership);
        $old = $membership->only(['status', 'notes', 'cancel_at_period_end']);

        $membership->fill(array_intersect_key($data, array_flip([
            'status', 'notes', 'cancel_at_period_end', 'trial_ends_at',
            'current_period_starts_at', 'current_period_ends_at', 'next_billing_date',
        ])));
        $membership->save();

        $this->auditLogger->log('client_membership.updated', $membership, $old, $membership->only(['status', 'notes', 'cancel_at_period_end']));

        return $membership->fresh()->load(['client', 'membershipPlan']);
    }

    public function pause(ClientMembership $membership): ClientMembership
    {
        $this->scope->assertTenantModel($membership);

        if ($membership->status !== ClientMembershipStatus::ACTIVE) {
            throw ValidationException::withMessages(['status' => ['Only active memberships can be paused.']]);
        }

        $membership->status = ClientMembershipStatus::PAUSED;
        $membership->paused_at = now();
        $membership->save();

        $this->auditLogger->log('client_membership.paused', $membership, null, ['status' => ClientMembershipStatus::PAUSED]);

        return $membership;
    }

    public function resume(ClientMembership $membership): ClientMembership
    {
        $this->scope->assertTenantModel($membership);

        if ($membership->status !== ClientMembershipStatus::PAUSED) {
            throw ValidationException::withMessages(['status' => ['Only paused memberships can be resumed.']]);
        }

        $membership->status = ClientMembershipStatus::ACTIVE;
        $membership->paused_at = null;
        $membership->save();

        $this->auditLogger->log('client_membership.resumed', $membership, null, ['status' => ClientMembershipStatus::ACTIVE]);

        return $membership;
    }

    public function cancel(ClientMembership $membership, bool $atPeriodEnd = false): ClientMembership
    {
        $this->scope->assertTenantModel($membership);

        if (in_array($membership->status, [ClientMembershipStatus::CANCELLED, ClientMembershipStatus::EXPIRED], true)) {
            throw ValidationException::withMessages(['status' => ['Membership is already cancelled or expired.']]);
        }

        if ($atPeriodEnd) {
            $membership->cancel_at_period_end = true;
            $membership->save();
        } else {
            $membership->status = ClientMembershipStatus::CANCELLED;
            $membership->cancelled_at = now();
            $membership->cancel_at_period_end = false;
            $membership->save();
        }

        $this->auditLogger->log('client_membership.cancelled', $membership, null, [
            'status' => $membership->status,
            'cancel_at_period_end' => $membership->cancel_at_period_end,
        ]);

        try {
            $this->marketingTriggers->fireMembershipCancelled($membership, $atPeriodEnd);
        } catch (\Throwable) {
            // Marketing automations must not block membership cancellation.
        }

        return $membership;
    }

    public function activeSummaryForClient(string $clientId): ?ClientMembership
    {
        return ClientMembership::query()
            ->with('membershipPlan')
            ->where('client_id', $clientId)
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->orderByDesc('started_at')
            ->first();
    }

    private function computePeriodEnd(Carbon $start, ?string $frequency): Carbon
    {
        return match ($frequency) {
            MembershipBillingFrequency::WEEKLY => $start->copy()->addWeek(),
            MembershipBillingFrequency::QUARTERLY => $start->copy()->addMonths(3),
            MembershipBillingFrequency::YEARLY => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }
}
