<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipPlanService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = MembershipPlan::query()->with('locations')->orderBy('name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): MembershipPlan
    {
        return $this->scope->findPlan($id);
    }

    public function create(array $data): MembershipPlan
    {
        $plan = DB::transaction(function () use ($data) {
            $plan = MembershipPlan::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? MembershipPlanStatus::ACTIVE,
                'plan_type' => $data['plan_type'] ?? 'membership',
                'billing_frequency' => $data['billing_frequency'] ?? null,
                'price_cents' => $data['price_cents'] ?? 0,
                'joining_fee_cents' => $data['joining_fee_cents'] ?? 0,
                'included_wallet_credit_cents' => $data['included_wallet_credit_cents'] ?? 0,
                'included_loyalty_points' => $data['included_loyalty_points'] ?? 0,
                'included_entitlement_quantity' => $data['included_entitlement_quantity'] ?? 0,
                'auto_renew' => $data['auto_renew'] ?? true,
                'grace_period_days' => $data['grace_period_days'] ?? null,
                'is_public' => $data['is_public'] ?? false,
                'applies_to_all_locations' => $data['applies_to_all_locations'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLocations($plan, $data);

            return $plan;
        });

        $this->auditLogger->log('membership_plan.created', $plan, null, $plan->only(['name', 'status', 'price_cents']));

        return $plan->fresh()->load('locations');
    }

    public function update(MembershipPlan $plan, array $data): MembershipPlan
    {
        $this->scope->assertTenantModel($plan);
        $old = $plan->only(['name', 'status', 'price_cents', 'billing_frequency']);

        DB::transaction(function () use ($plan, $data) {
            $plan->fill(array_intersect_key($data, array_flip([
                'name', 'description', 'status', 'plan_type', 'billing_frequency',
                'price_cents', 'joining_fee_cents', 'included_wallet_credit_cents',
                'included_loyalty_points', 'included_entitlement_quantity',
                'auto_renew', 'grace_period_days', 'is_public', 'applies_to_all_locations', 'notes',
            ])));
            $plan->save();

            if (array_key_exists('location_ids', $data) || array_key_exists('applies_to_all_locations', $data)) {
                $this->syncLocations($plan, $data);
            }
        });

        $this->auditLogger->log('membership_plan.updated', $plan, $old, $plan->only(['name', 'status', 'price_cents', 'billing_frequency']));

        return $plan->fresh()->load('locations');
    }

    public function archive(MembershipPlan $plan): MembershipPlan
    {
        $this->scope->assertTenantModel($plan);
        $plan->status = MembershipPlanStatus::ARCHIVED;
        $plan->archived_at = now();
        $plan->save();

        $this->auditLogger->log('membership_plan.archived', $plan, null, ['status' => MembershipPlanStatus::ARCHIVED]);

        return $plan;
    }

    private function syncLocations(MembershipPlan $plan, array $data): void
    {
        $appliesToAll = $data['applies_to_all_locations'] ?? $plan->applies_to_all_locations;

        if ($appliesToAll) {
            $plan->locations()->detach();

            return;
        }

        $locationIds = $data['location_ids'] ?? [];
        foreach ($locationIds as $locationId) {
            $this->scope->findLocation($locationId);
        }

        $sync = [];
        foreach ($locationIds as $locationId) {
            $sync[$locationId] = ['id' => (string) Str::uuid()];
        }

        $plan->locations()->sync($sync);
    }
}
