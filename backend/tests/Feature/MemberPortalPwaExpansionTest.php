<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Enums\PackageGiftCodeStatus;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageGiftCode;
use App\Domains\Memberships\Models\PackageProduct;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MemberPortalPwaExpansionTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_member_dashboard_visits_loyalty_purchase_and_gift_flow(): void
    {
        $ctx = $this->seedTenantContext([
            'memberships.view',
            'memberships.manage',
            'payments.view',
            'payments.manage',
        ]);

        app(TenantContext::class)->set($ctx['tenant']);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Public Club',
            'status' => MembershipPlanStatus::ACTIVE,
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 6500,
            'joining_fee_cents' => 0,
            'included_wallet_credit_cents' => 0,
            'included_loyalty_points' => 0,
            'is_public' => true,
        ]);

        $product = PackageProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Six Cuts',
            'status' => MembershipPlanStatus::ACTIVE,
            'price_cents' => 18000,
            'included_quantity' => 6,
            'is_public' => true,
        ]);

        $owner = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Owner',
            'last_name' => 'Member',
            'email' => 'owner-member@example.test',
            'phone' => '+447700900801',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $friend = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Friend',
            'last_name' => 'Member',
            'email' => 'friend-member@example.test',
            'phone' => '+447700900802',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $owned = ClientPackage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $owner->id,
            'package_product_id' => $product->id,
            'status' => 'active',
            'source' => ClientPackageSource::MANUAL,
            'purchased_at' => now(),
            'starts_at' => now(),
            'quantity_total' => 6,
            'quantity_remaining' => 6,
        ]);

        $ownerToken = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'owner-member@example.test',
            '+447700900801',
        );
        $ownerHeaders = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$ownerToken,
        ];

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/member/dashboard')
            ->assertOk()
            ->assertJsonPath('data.client.email', 'owner-member@example.test')
            ->assertJsonPath('data.packages.0.id', $owned->id)
            ->assertJsonPath('data.offers.plans.0.id', $plan->id)
            ->assertJsonPath('data.offers.packages.0.id', $product->id);

        $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/member/check-in', ['location_id' => $ctx['location']->id])
            ->assertOk();

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/member/visits')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($ownerHeaders)
            ->getJson('/api/v1/member/loyalty')
            ->assertOk()
            ->assertJsonPath('data.balance', 10);

        $purchase = $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/member/purchases', [
                'offer_type' => 'plan',
                'offer_id' => $plan->id,
            ])
            ->assertCreated();

        $this->assertSame(PaymentTransactionStatus::SUCCEEDED, $purchase->json('data.status'));
        $this->assertDatabaseHas('payment_transactions', [
            'client_id' => $owner->id,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 6500,
        ]);
        $this->assertDatabaseHas('client_memberships', [
            'client_id' => $owner->id,
            'membership_plan_id' => $plan->id,
        ]);

        $gift = $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/member/gifts', [
                'client_package_id' => $owned->id,
                'quantity' => 2,
                'recipient_name' => 'Friend',
            ])
            ->assertCreated();

        $code = $gift->json('data.code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('package_gift_codes', [
            'code' => $code,
            'status' => PackageGiftCodeStatus::OPEN,
            'from_client_id' => $owner->id,
        ]);

        $owned->refresh();
        $this->assertSame(4.0, (float) $owned->quantity_remaining);

        $friendToken = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'friend-member@example.test',
            '+447700900802',
        );

        $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$friendToken,
        ])
            ->postJson('/api/v1/member/gifts/claim', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.status', PackageGiftCodeStatus::CLAIMED);

        $this->assertDatabaseHas('client_packages', [
            'client_id' => $friend->id,
            'package_product_id' => $product->id,
            'source' => ClientPackageSource::GIFT,
            'quantity_remaining' => 2,
        ]);

        $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/member/push-subscriptions', [
                'endpoint' => 'https://push.example.test/endpoint-1',
                'keys' => ['p256dh' => 'abc', 'auth' => 'def'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.subscribed', true);

        $this->assertDatabaseHas('member_push_subscriptions', [
            'client_id' => $owner->id,
            'endpoint' => 'https://push.example.test/endpoint-1',
        ]);
    }
}
