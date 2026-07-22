<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;
use App\Shared\Tenancy\TenantContext;

/**
 * Public membership education + catalog for booking/join surfaces (no auth).
 */
class MembershipPublicLandingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyRedemptionSettingsService $loyaltySettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function landing(): array
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            abort(422, 'Tenant context is required.');
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $offers = $this->publicOffers();
        $loyalty = $this->loyaltySettings->get();

        return [
            'tenant' => [
                'name' => $tenant->trading_name ?: $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $tenant->getBranding() ?? [],
            ],
            'paths' => [
                'book' => '/book/'.$tenant->slug,
                'join' => '/join/'.$tenant->slug,
                'member' => '/member/'.$tenant->slug,
            ],
            'education' => [
                [
                    'key' => 'plan',
                    'title' => 'Membership plan',
                    'summary' => 'Ongoing membership — access and perks over time.',
                    'best_for' => 'Regulars who visit often and want member rates plus recurring benefits.',
                    'how_it_works' => 'Pay on a billing cycle (e.g. monthly). You stay a member while the subscription is active. Plans may include wallet credit or loyalty points each period.',
                ],
                [
                    'key' => 'package',
                    'title' => 'Visit package',
                    'summary' => 'Prepaid visits — buy a bundle and use sessions until they are gone.',
                    'best_for' => 'A set course of treatments when you know how many visits you need.',
                    'how_it_works' => 'Pay once for a fixed number of visits. Each appointment uses one (or more) credits. No recurring fee.',
                ],
                [
                    'key' => 'loyalty',
                    'title' => 'Loyalty points',
                    'summary' => 'Free points you earn — not something you buy.',
                    'best_for' => 'Every guest: check in, earn points, redeem toward future visits when enabled.',
                    'how_it_works' => 'Points build as you visit. They stack with memberships and packages; they are a reward layer, not a replacement for either.',
                ],
            ],
            'comparison' => [
                ['aspect' => 'What you pay', 'plan' => 'Recurring fee', 'package' => 'One-time price', 'loyalty' => 'Free to join'],
                ['aspect' => 'What you get', 'plan' => 'Member status + perks', 'package' => 'Visit credits', 'loyalty' => 'Points to redeem'],
                ['aspect' => 'Best when', 'plan' => 'You visit regularly', 'package' => 'You need N visits', 'loyalty' => 'You want rewards'],
            ],
            'offers' => $offers,
            'loyalty' => [
                'redemption_enabled' => (bool) $loyalty->is_loyalty_redemption_enabled,
                'points_per_redemption_block' => (int) $loyalty->points_per_redemption_block,
                'value_cents_per_block' => (int) $loyalty->value_cents_per_block,
                'crm_join_signup_points' => (int) ($loyalty->crm_join_signup_points ?? 0),
            ],
        ];
    }

    /**
     * @return array{plans: list<array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function publicOffers(): array
    {
        $plans = MembershipPlan::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        $packages = PackageProduct::query()
            ->where('status', MembershipPlanStatus::ACTIVE)
            ->where('is_public', true)
            ->orderBy('name')
            ->get();

        return [
            'plans' => $plans->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_cents' => (int) $plan->price_cents,
                'billing_frequency' => $plan->billing_frequency,
                'joining_fee_cents' => (int) $plan->joining_fee_cents,
                'included_wallet_credit_cents' => (int) ($plan->included_wallet_credit_cents ?? 0),
                'included_loyalty_points' => (int) ($plan->included_loyalty_points ?? 0),
                'best_for' => 'Ongoing membership with recurring benefits',
            ])->all(),
            'packages' => $packages->map(fn (PackageProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price_cents' => (int) $product->price_cents,
                'included_quantity' => (float) $product->included_quantity,
                'expiry_days' => $product->expiry_days,
                'best_for' => 'Prepaid visits — use until gone',
            ])->all(),
        ];
    }
}
