<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Enums\PackageRedemptionState;
use App\Domains\Memberships\Enums\WalletEntryDirection;
use App\Domains\Memberships\Enums\WalletEntryType;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientPackageRedemption;
use App\Domains\Memberships\Models\ClientWalletEntry;
use App\Domains\Memberships\Models\MembershipLoyaltySetting;
use App\Domains\Memberships\Models\PackageProduct;
use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module9BMembershipRedemptionAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function modulePermissions(): array
    {
        return [
            'booking.view',
            'booking.manage',
            'crm.view',
            'pos.view',
            'pos.manage',
            'pos.checkout.complete',
            'pos.checkout.reopen',
            'pos.refund',
            'memberships.view',
            'memberships.manage',
        ];
    }

    protected function seedMembershipFixture(array $ctx): array
    {
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Member',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Blow Dry',
            'duration_minutes' => 45,
            'base_price_cents' => 4000,
            'is_active' => true,
        ]);

        $packageProduct = PackageProduct::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => '5 Pack',
            'status' => 'active',
            'price_cents' => 15000,
            'included_quantity' => 5,
        ]);

        DB::table('package_product_services')->insert([
            'id' => (string) Str::uuid(),
            'package_product_id' => $packageProduct->id,
            'booking_service_id' => $service->id,
            'quantity_per_redemption' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientPackage = ClientPackage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'package_product_id' => $packageProduct->id,
            'status' => ClientPackageStatus::ACTIVE,
            'source' => 'manual',
            'quantity_total' => 5,
            'quantity_remaining' => 3,
            'purchased_at' => now(),
        ]);

        MembershipLoyaltySetting::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'is_loyalty_redemption_enabled' => true,
            'points_per_redemption_block' => 100,
            'value_cents_per_block' => 1000,
        ]);

        ClientWalletEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'entry_type' => WalletEntryType::MANUAL_CREDIT,
            'direction' => WalletEntryDirection::CREDIT,
            'amount_cents' => 2500,
            'balance_effective_at' => now(),
        ]);

        ClientLoyaltyEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'entry_type' => LoyaltyEntryType::MANUAL_AWARD,
            'direction' => LoyaltyEntryDirection::CREDIT,
            'points' => 250,
            'effective_at' => now(),
        ]);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => Carbon::now()->subHour(),
            'ends_at' => Carbon::now(),
            'status' => Appointment::STATUS_CHECKED_IN,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-9B01',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            'billing_settlement_status' => BillingSettlementStatus::UNSETTLED,
        ]);

        $serviceLine = AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => 4000,
            'sort_order' => 0,
        ]);

        return compact('client', 'service', 'clientPackage', 'appointment', 'serviceLine');
    }

    protected function createCheckoutWithClient(array $ctx, string $clientId): string
    {
        return $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', [
                'location_id' => $ctx['location']->id,
                'client_id' => $clientId,
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_eligible_packages_and_loyalty_settings(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixture = $this->seedMembershipFixture($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/clients/{$fixture['client']->id}/eligible-packages?booking_service_id={$fixture['service']->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/clients/{$fixture['client']->id}/wallet-summary")
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 2500);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/memberships/clients/{$fixture['client']->id}/loyalty-summary")
            ->assertOk()
            ->assertJsonPath('data.points_balance', 250)
            ->assertJsonPath('data.redeemable_value_cents', 2000);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/memberships/settings/loyalty-redemption', [
                'is_loyalty_redemption_enabled' => true,
                'points_per_redemption_block' => 50,
                'value_cents_per_block' => 500,
            ])
            ->assertOk()
            ->assertJsonPath('data.points_per_redemption_block', 50);

        $this->assertDatabaseHas('audit_logs', ['action' => 'loyalty_redemption_settings.updated']);
    }

    public function test_booking_package_reserve_and_release(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixture = $this->seedMembershipFixture($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$fixture['appointment']->id}/service-lines/{$fixture['serviceLine']->id}/package-reserve", [
                'client_package_id' => $fixture['clientPackage']->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('client_package_redemptions', [
            'client_package_id' => $fixture['clientPackage']->id,
            'state' => PackageRedemptionState::RESERVED,
        ]);

        $fixture['clientPackage']->refresh();
        $this->assertEquals(2.0, (float) $fixture['clientPackage']->quantity_remaining);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$fixture['appointment']->id}/service-lines/{$fixture['serviceLine']->id}/package-release")
            ->assertOk();

        $fixture['clientPackage']->refresh();
        $this->assertEquals(3.0, (float) $fixture['clientPackage']->quantity_remaining);

        $this->assertDatabaseHas('audit_logs', ['action' => 'client_package.reserved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_package.released']);
    }

    public function test_pos_wallet_and_loyalty_application(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixture = $this->seedMembershipFixture($ctx);
        $checkoutId = $this->createCheckoutWithClient($ctx, $fixture['client']->id);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Blow dry',
                'unit_price_cents' => 4000,
                'booking_service_id' => $fixture['service']->id,
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/apply-wallet", ['amount_cents' => 1500])
            ->assertOk()
            ->assertJsonPath('data.wallet_credit_cents', 1500)
            ->assertJsonPath('data.amount_due_cents', 2500);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/remove-wallet")
            ->assertOk()
            ->assertJsonPath('data.wallet_credit_cents', 0)
            ->assertJsonPath('data.amount_due_cents', 4000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/apply-loyalty", ['points' => 100])
            ->assertOk()
            ->assertJsonPath('data.loyalty_points_redeemed', 100)
            ->assertJsonPath('data.loyalty_discount_cents', 1000)
            ->assertJsonPath('data.amount_due_cents', 3000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/remove-loyalty")
            ->assertOk()
            ->assertJsonPath('data.loyalty_points_redeemed', 0);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.wallet_applied']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'loyalty.redeemed']);
    }

    public function test_pos_package_apply_and_imported_reservation(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixture = $this->seedMembershipFixture($ctx);

        app(PackageEntitlementService::class)->reserveForServiceLine(
            $fixture['appointment'],
            $fixture['serviceLine'],
            $fixture['clientPackage']->id,
            1.0,
            $ctx['teamMember']->id,
        );

        $checkoutId = $this->createCheckoutWithClient($ctx, $fixture['client']->id);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $fixture['appointment']->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.package_covered_cents', 4000)
            ->assertJsonPath('data.amount_due_cents', 0);

        $redemptionCount = ClientPackageRedemption::query()
            ->where('client_package_id', $fixture['clientPackage']->id)
            ->where('state', PackageRedemptionState::RESERVED)
            ->count();

        $this->assertEquals(1, $redemptionCount);

        $lineId = CommerceCheckoutLine::query()->where('checkout_id', $checkoutId)->value('id');

        $checkoutId2 = $this->createCheckoutWithClient($ctx, $fixture['client']->id);
        $line = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/lines/service", [
                'description' => 'Direct',
                'unit_price_cents' => 4000,
                'booking_service_id' => $fixture['service']->id,
            ])
            ->assertOk()
            ->json('data.lines.0.id');

        $fixture['clientPackage']->update(['quantity_remaining' => 2]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/lines/{$line}/apply-package", [
                'client_package_id' => $fixture['clientPackage']->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.package_covered_cents', 4000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/lines/{$line}/remove-package")
            ->assertOk()
            ->assertJsonPath('data.package_covered_cents', 0);
    }

    public function test_checkout_void_reopen_and_completion_redemption(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $fixture = $this->seedMembershipFixture($ctx);
        $checkoutId = $this->createCheckoutWithClient($ctx, $fixture['client']->id);

        $line = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Service',
                'unit_price_cents' => 3000,
                'booking_service_id' => $fixture['service']->id,
            ])
            ->assertOk()
            ->json('data.lines.0.id');

        $fixture['clientPackage']->update(['quantity_remaining' => 2]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/apply-wallet", ['amount_cents' => 500])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/{$line}/apply-package", [
                'client_package_id' => $fixture['clientPackage']->id,
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/void")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::VOIDED);

        $this->assertDatabaseHas('client_package_redemptions', [
            'client_package_id' => $fixture['clientPackage']->id,
            'state' => PackageRedemptionState::RELEASED,
        ]);

        $checkoutId2 = $this->createCheckoutWithClient($ctx, $fixture['client']->id);
        $line2 = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/lines/service", [
                'description' => 'Complete me',
                'unit_price_cents' => 2000,
                'booking_service_id' => $fixture['service']->id,
            ])
            ->assertOk()
            ->json('data.lines.0.id');

        $fixture['clientPackage']->update(['quantity_remaining' => 2]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/lines/{$line2}/apply-package", [
                'client_package_id' => $fixture['clientPackage']->id,
            ])
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::COMPLETED);

        $this->assertDatabaseHas('client_package_redemptions', [
            'checkout_id' => $checkoutId2,
            'state' => PackageRedemptionState::REDEEMED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId2}/reopen", ['reason' => 'Correction'])
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::OPEN)
            ->assertJsonPath('data.package_covered_cents', 0);

        $this->assertDatabaseHas('client_package_redemptions', [
            'state' => PackageRedemptionState::RESTORED,
        ]);
    }

    public function test_tenant_isolation_and_permission_gates(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/memberships/clients/'.Str::uuid().'/wallet-summary')
            ->assertNotFound();
    }

    public function test_pos_membership_manage_permission_gate(): void
    {
        $viewOnly = $this->seedTenantContext(['memberships.view', 'pos.view']);
        $checkout = CommerceCheckout::withoutGlobalScopes()->create([
            'tenant_id' => $viewOnly['tenant']->id,
            'checkout_number' => 'CHK-VIEW',
            'location_id' => $viewOnly['location']->id,
            'status' => CheckoutStatus::OPEN,
            'currency' => 'GBP',
            'subtotal_cents' => 0,
            'total_cents' => 0,
            'amount_due_cents' => 0,
        ]);

        $this->withTenantAuth($viewOnly['token'])
            ->getJson("/api/v1/admin/pos/checkouts/{$checkout->id}/membership-options")
            ->assertOk();

        $this->withTenantAuth($viewOnly['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkout->id}/apply-wallet", ['amount_cents' => 100])
            ->assertForbidden();
    }
}
