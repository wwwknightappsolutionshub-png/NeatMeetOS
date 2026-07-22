<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TenantSubscription;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantTierService $tiers,
    ) {}

    public function getCurrent(): TenantSubscription
    {
        $tenantId = $this->tenantId();

        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => ['No subscription record found for this tenant.'],
            ]);
        }

        return $subscription;
    }

    public function listAvailablePlans(): Collection
    {
        $subscription = $this->getCurrent();

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->whereIn('slug', TenantSignupService::TIER_SLUGS)
            ->orderByRaw("CASE slug WHEN 'basic' THEN 1 WHEN 'pro' THEN 2 WHEN 'diamond' THEN 3 ELSE 9 END")
            ->get()
            ->each(function (SubscriptionPlan $plan) use ($subscription) {
                $can = $this->tiers->canSubscribeToPlan($subscription, $plan->slug);
                $plan->setAttribute('can_subscribe', $can);
                $plan->setAttribute(
                    'locked_reason',
                    $can
                        ? null
                        : 'Available after your 30-day trial ends, or when a platform admin unlocks Pro/Diamond.',
                );
            });
    }

    public function changePlan(string $planSlug): TenantSubscription
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        $subscription = $this->getCurrent();

        return $this->tiers->applyPlan($tenant, $subscription, $planSlug);
    }

    private function tenantId(): string
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $tenantId;
    }
}
