<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class TenantTierService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {}

    public function canSubscribeToPlan(TenantSubscription $subscription, string $planSlug): bool
    {
        if ($planSlug === 'basic') {
            return true;
        }

        if ($subscription->tier_unlocked) {
            return true;
        }

        if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Platform admin: unlock Pro/Diamond early and optionally move the tenant onto that plan.
     */
    public function unlockTier(Tenant $tenant, ?string $activatePlanSlug = null): TenantSubscription
    {
        $subscription = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => ['Tenant has no subscription record.'],
            ]);
        }

        $old = $subscription->toArray();
        $subscription->tier_unlocked = true;
        $subscription->tier_unlocked_at = now();
        $subscription->save();

        if ($activatePlanSlug !== null && $activatePlanSlug !== '') {
            $this->applyPlan($tenant, $subscription, $activatePlanSlug, true);
        }

        $this->tenantContext->set($tenant);
        $this->audit->log('platform.tenant.tier_unlocked', $tenant, $old, $subscription->fresh()->toArray());

        return $subscription->fresh(['plan']);
    }

    public function applyPlan(Tenant $tenant, TenantSubscription $subscription, string $planSlug, bool $force = false): TenantSubscription
    {
        if (! in_array($planSlug, TenantSignupService::TIER_SLUGS, true)) {
            throw ValidationException::withMessages([
                'plan' => ['Plan must be basic, pro, or diamond.'],
            ]);
        }

        if (! $force && ! $this->canSubscribeToPlan($subscription, $planSlug)) {
            throw ValidationException::withMessages([
                'plan' => ['Pro and Diamond are locked until your 30-day trial ends, unless a platform admin unlocks them.'],
            ]);
        }

        $plan = SubscriptionPlan::query()->where('slug', $planSlug)->where('is_active', true)->first();
        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => ['Plan not found.']]);
        }

        $subscription->subscription_plan_id = $plan->id;
        $subscription->desired_plan_slug = $planSlug;
        $subscription->save();

        $tenant->subscription_plan_id = $plan->id;
        $tenant->save();

        return $subscription->fresh(['plan']);
    }
}
