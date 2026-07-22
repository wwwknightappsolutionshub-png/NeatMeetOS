<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientWalletEntry;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module9AMembershipsAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function membershipsPermissions(): array
    {
        return [
            'memberships.view',
            'memberships.manage',
            'memberships.reporting.view',
        ];
    }

    protected function membershipsViewOnly(): array
    {
        return ['memberships.view', 'memberships.reporting.view'];
    }

    public function test_membership_plan_crud_and_archive(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/plans', [
                'name' => 'VIP Club',
                'billing_frequency' => MembershipBillingFrequency::MONTHLY,
                'price_cents' => 5000,
                'included_wallet_credit_cents' => 1000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'VIP Club');

        $id = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/memberships/plans/{$id}", ['price_cents' => 5500])
            ->assertOk()
            ->assertJsonPath('data.price_cents', 5500);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/memberships/plans/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('audit_logs', ['action' => 'membership_plan.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership_plan.archived']);
    }

    public function test_package_product_crud_with_service_restrictions(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Test Service',
            'duration_minutes' => 60,
            'base_price_cents' => 3000,
            'is_active' => true,
        ]);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/packages', [
                'name' => '5 Pack',
                'price_cents' => 12000,
                'included_quantity' => 5,
                'service_restrictions' => [
                    ['booking_service_id' => $service->id, 'quantity_per_redemption' => 1],
                ],
            ])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->assertDatabaseHas('package_product_services', [
            'package_product_id' => $id,
            'booking_service_id' => $service->id,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/memberships/packages/{$id}/archive")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'package_product.created']);
    }

    public function test_client_membership_assigns_snapshot_and_benefits(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Bonus Plan',
            'status' => 'active',
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 4000,
            'included_wallet_credit_cents' => 800,
            'included_loyalty_points' => 50,
        ]);

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/client-memberships', [
                'client_id' => $client->id,
                'membership_plan_id' => $plan->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price_cents_snapshot', 4000)
            ->assertJsonPath('data.status', ClientMembershipStatus::ACTIVE);

        $this->assertDatabaseHas('client_wallet_entries', [
            'client_id' => $client->id,
            'entry_type' => 'membership_credit',
            'amount_cents' => 800,
        ]);

        $this->assertDatabaseHas('client_loyalty_entries', [
            'client_id' => $client->id,
            'entry_type' => 'membership_bonus',
            'points' => 50,
        ]);

        $membershipId = $response->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/memberships/client-memberships/{$membershipId}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', ClientMembershipStatus::PAUSED);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/memberships/client-memberships/{$membershipId}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', ClientMembershipStatus::ACTIVE);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/memberships/client-memberships/{$membershipId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', ClientMembershipStatus::CANCELLED);
    }

    public function test_wallet_manual_credit_debit_and_balance(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Wallet',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/wallet-entries', [
                'client_id' => $client->id,
                'direction' => 'credit',
                'amount_cents' => 2000,
                'notes' => 'Manual top-up',
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/wallet-entries', [
                'client_id' => $client->id,
                'direction' => 'debit',
                'amount_cents' => 500,
                'notes' => 'Manual spend',
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/clients/{$client->id}/wallet")
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 1500);

        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.entry_created']);
    }

    public function test_loyalty_manual_award_deduction_and_balance(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Loyalty',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/loyalty-entries', [
                'client_id' => $client->id,
                'direction' => 'credit',
                'points' => 100,
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/loyalty-entries', [
                'client_id' => $client->id,
                'direction' => 'debit',
                'points' => 30,
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/clients/{$client->id}/loyalty")
            ->assertOk()
            ->assertJsonPath('data.points_balance', 70);
    }

    public function test_client_package_redeem_restore_and_zero_guard(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Package',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $product = PackageProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => '3 Pack',
            'status' => 'active',
            'price_cents' => 9000,
            'included_quantity' => 3,
        ]);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/client-packages', [
                'client_id' => $client->id,
                'package_product_id' => $product->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_remaining', 3);

        $packageId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/memberships/client-packages/{$packageId}/redeem", [
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 2);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/memberships/client-packages/{$packageId}/restore", [
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 3);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/memberships/client-packages/{$packageId}/redeem", [
                'quantity' => 5,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', ['action' => 'client_package.redeemed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_package.restored']);
    }

    public function test_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $otherPlan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Other Tenant Plan',
            'status' => 'active',
            'price_cents' => 1000,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/plans/{$otherPlan->id}")
            ->assertNotFound();
    }

    public function test_permission_gate_for_manage(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsViewOnly());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/memberships/plans', [
                'name' => 'Blocked',
                'price_cents' => 1000,
            ])
            ->assertForbidden();
    }

    public function test_summary_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->membershipsPermissions());

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Summary',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'MRR Plan',
            'status' => 'active',
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 6500,
        ]);

        ClientMembership::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => ClientMembershipStatus::ACTIVE,
            'started_at' => now(),
            'price_cents_snapshot' => 6500,
        ]);

        ClientWalletEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'entry_type' => 'manual_credit',
            'direction' => 'credit',
            'amount_cents' => 1000,
            'balance_effective_at' => now(),
        ]);

        $product = PackageProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Pack',
            'status' => 'active',
            'price_cents' => 5000,
            'included_quantity' => 2,
        ]);

        ClientPackage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'package_product_id' => $product->id,
            'status' => ClientPackageStatus::ACTIVE,
            'quantity_total' => 2,
            'quantity_remaining' => 1,
        ]);

        ClientLoyaltyEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'entry_type' => 'manual_award',
            'direction' => 'credit',
            'points' => 25,
            'effective_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/memberships/summary')
            ->assertOk()
            ->assertJsonPath('data.active_subscriptions_count', 1)
            ->assertJsonPath('data.mrr_estimate_cents', 6500)
            ->assertJsonPath('data.wallet_liability_cents', 1000)
            ->assertJsonPath('data.outstanding_package_balances_count', 1)
            ->assertJsonPath('data.loyalty_points_issued_total', 25);
    }
}
