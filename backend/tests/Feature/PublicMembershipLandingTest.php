<?php

namespace Tests\Feature;

use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PublicMembershipLandingTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_public_memberships_landing_returns_education_and_public_offers(): void
    {
        $ctx = $this->seedTenantContext(['memberships.view', 'memberships.manage']);

        MembershipPlan::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Public VIP',
            'status' => MembershipPlanStatus::ACTIVE,
            'is_public' => true,
            'billing_frequency' => 'monthly',
            'price_cents' => 2900,
            'joining_fee_cents' => 0,
            'included_wallet_credit_cents' => 500,
            'included_loyalty_points' => 50,
        ]);

        MembershipPlan::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Private Plan',
            'status' => MembershipPlanStatus::ACTIVE,
            'is_public' => false,
            'billing_frequency' => 'monthly',
            'price_cents' => 9900,
            'joining_fee_cents' => 0,
        ]);

        PackageProduct::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Six Cuts',
            'status' => MembershipPlanStatus::ACTIVE,
            'is_public' => true,
            'price_cents' => 12000,
            'included_quantity' => 6,
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/memberships')
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', $ctx['tenant']->slug)
            ->assertJsonCount(3, 'data.education')
            ->assertJsonPath('data.education.0.key', 'plan')
            ->assertJsonPath('data.offers.plans.0.name', 'Public VIP')
            ->assertJsonCount(1, 'data.offers.plans')
            ->assertJsonPath('data.offers.packages.0.name', 'Six Cuts')
            ->assertJsonStructure([
                'data' => [
                    'comparison',
                    'loyalty',
                    'paths' => ['book', 'join', 'member'],
                ],
            ]);
    }
}
