<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Domains\Inventory\Models\InventoryLevel;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\ServiceInventoryConsumptionRule;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module8APosAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function posPermissions(): array
    {
        return [
            'booking.view',
            'booking.manage',
            'crm.view',
            'staff.view',
            'payments.view',
            'payments.manage',
            'inventory.view',
            'inventory.manage',
            'inventory.adjust',
            'pos.view',
            'pos.manage',
            'pos.checkout.complete',
        ];
    }

    protected function seedEligibleAppointment(array $ctx, array $overrides = []): Appointment
    {
        if (! StaffProfile::withoutGlobalScopes()->where('team_member_id', $ctx['teamMember']->id)->exists()) {
            StaffProfile::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'is_bookable' => true,
            ]);

            $ctx['teamMember']->operatingLocations()->sync([$ctx['location']->id]);

            foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
                StaffAvailabilityRule::withoutGlobalScopes()->create([
                    'tenant_id' => $ctx['tenant']->id,
                    'team_member_id' => $ctx['teamMember']->id,
                    'location_id' => $ctx['location']->id,
                    'workspace_id' => $ctx['workspace']->id,
                    'day_of_week' => $day,
                    'start_time' => '08:00',
                    'end_time' => '20:00',
                    'is_active' => true,
                ]);
            }
        }

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'POS Colour',
            'duration_minutes' => 90,
            'base_price_cents' => 8000,
            'deposit_required' => true,
            'deposit_amount_cents' => 2000,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'POS',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $startsAt = Carbon::now()->subHour();

        $appointment = Appointment::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(90),
            'status' => Appointment::STATUS_CHECKED_IN,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-TESTPOS01',
            'deposit_status' => Appointment::DEPOSIT_SATISFIED,
            'deposit_required_cents' => 2000,
            'billing_settlement_status' => BillingSettlementStatus::UNSETTLED,
        ], $overrides));

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => 8000,
            'sort_order' => 0,
        ]);

        return $appointment->fresh(['serviceLines']);
    }

    protected function seedCollectedDeposit(Appointment $appointment, int $cents = 2000): CommerceDepositRecord
    {
        return CommerceDepositRecord::withoutGlobalScopes()->create([
            'tenant_id' => $appointment->tenant_id,
            'appointment_id' => $appointment->id,
            'booking_deposit_status' => Appointment::DEPOSIT_SATISFIED,
            'required_cents' => $cents,
            'collected_cents' => $cents,
            'lifecycle_state' => DepositLifecycleState::COLLECTED,
            'collected_at' => now(),
        ]);
    }

    protected function createCheckout(array $ctx, array $payload = []): string
    {
        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/pos/checkouts', array_merge([
                'location_id' => $ctx['location']->id,
            ], $payload));

        $response->assertCreated();

        return $response->json('data.id');
    }

    public function test_checkout_lifecycle_and_line_crud(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());

        $checkoutId = $this->createCheckout($ctx, [
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'first_name' => 'Walk',
                'last_name' => 'In',
                'is_active' => true,
            ])->id,
            'notes' => 'Front desk',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/pos/checkouts/{$checkoutId}", ['notes' => 'Updated note'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated note');

        $service = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Blow dry',
                'unit_price_cents' => 3500,
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal_cents', 3500);

        $lineId = collect($service->json('data.lines'))->first()['id'];

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'POS Shampoo',
            'item_type' => InventoryItemType::RETAIL,
            'retail_price_cents' => 1200,
            'status' => 'active',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/retail", [
                'inventory_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal_cents', 5900);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/{$lineId}", [
                'quantity' => 2,
                'unit_price_cents' => 3000,
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal_cents', 8400);

        $this->withTenantAuth($ctx['token'])
            ->deleteJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/{$lineId}")
            ->assertOk()
            ->assertJsonPath('data.subtotal_cents', 2400);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.line_added']);
    }

    public function test_appointment_import_and_duplicate_prevention(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx);
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.client.id', $appointment->client_id)
            ->assertJsonCount(1, 'data.linked_appointments');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.appointment_imported']);
    }

    public function test_ineligible_appointment_import_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx, [
            'status' => Appointment::STATUS_CANCELLED,
        ]);
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ])
            ->assertStatus(422);
    }

    public function test_deposit_credit_application(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx);
        $this->seedCollectedDeposit($appointment, 2000);
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.total_cents', 8000);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/apply-deposit-credit")
            ->assertOk()
            ->assertJsonPath('data.deposit_credit_cents', 2000)
            ->assertJsonPath('data.total_cents', 6000);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.deposit_credit_applied']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'deposit.applied']);
    }

    public function test_split_payments_and_completion(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx);
        $this->seedCollectedDeposit($appointment, 2000);
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/apply-deposit-credit");

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/payments", [
                'tenders' => [
                    ['amount_cents' => 3000, 'payment_method_type' => 'cash'],
                    ['amount_cents' => 3000, 'payment_method_type' => 'card_manual'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_due_cents', 0);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::COMPLETED);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'billing_settlement_status' => BillingSettlementStatus::SETTLED,
        ]);

        $this->assertDatabaseHas('commerce_deposit_records', [
            'appointment_id' => $appointment->id,
            'lifecycle_state' => DepositLifecycleState::APPLIED_TO_CHECKOUT,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.completed']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'checkout.completed']);
    }

    public function test_completion_rejected_when_amount_due_remains(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Service',
                'unit_price_cents' => 4000,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertStatus(422);
    }

    public function test_retail_completion_consumes_stock(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $checkoutId = $this->createCheckout($ctx);

        $item = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Retail POS Item',
            'item_type' => InventoryItemType::RETAIL,
            'retail_price_cents' => 1500,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $item->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 10,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/retail", [
                'inventory_item_id' => $item->id,
                'quantity' => 2,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/payments", [
                'tenders' => [
                    ['amount_cents' => 3000, 'payment_method_type' => 'cash'],
                ],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertOk();

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => InventoryMovementType::SALE,
        ]);

        $this->assertDatabaseHas('inventory_levels', [
            'inventory_item_id' => $item->id,
            'on_hand_quantity' => 8,
        ]);
    }

    public function test_service_completion_triggers_consumption_rules(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx);
        $serviceLine = $appointment->serviceLines->first();
        $checkoutId = $this->createCheckout($ctx);

        $proItem = InventoryItem::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Colour Tube',
            'item_type' => InventoryItemType::PROFESSIONAL,
            'status' => 'active',
        ]);

        InventoryLevel::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'inventory_item_id' => $proItem->id,
            'location_id' => $ctx['location']->id,
            'on_hand_quantity' => 20,
        ]);

        ServiceInventoryConsumptionRule::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'booking_service_id' => $serviceLine->booking_service_id,
            'inventory_item_id' => $proItem->id,
            'quantity_required' => 2,
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/payments", [
                'tenders' => [
                    ['amount_cents' => 8000, 'payment_method_type' => 'cash'],
                ],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertOk();

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $proItem->id,
            'movement_type' => InventoryMovementType::SERVICE_CONSUMPTION,
        ]);
    }

    public function test_void_draft_checkout(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/void")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::VOIDED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'checkout.voided']);
    }

    public function test_deposit_cannot_be_applied_twice_across_completed_checkouts(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());
        $appointment = $this->seedEligibleAppointment($ctx);
        $deposit = $this->seedCollectedDeposit($appointment, 2000);

        $firstId = $this->createCheckout($ctx);
        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$firstId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ]);
        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$firstId}/apply-deposit-credit");
        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$firstId}/payments", [
                'tenders' => [['amount_cents' => 6000, 'payment_method_type' => 'cash']],
            ]);
        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$firstId}/complete")
            ->assertOk();

        $secondId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$secondId}/import-appointment", [
                'appointment_id' => $appointment->id,
            ])
            ->assertStatus(422);
    }

    public function test_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());

        $otherCheckout = CommerceCheckout::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'location_id' => Location::withoutGlobalScopes()->where('tenant_id', $ctx['otherTenant']->id)->first()?->id
                ?? Location::withoutGlobalScopes()->create([
                    'tenant_id' => $ctx['otherTenant']->id,
                    'name' => 'Other',
                    'slug' => 'other-loc',
                    'timezone' => 'Europe/London',
                    'is_active' => true,
                ])->id,
            'client_id' => Client::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['otherTenant']->id,
                'first_name' => 'Other',
                'last_name' => 'Client',
                'is_active' => true,
            ])->id,
            'status' => CheckoutStatus::DRAFT,
            'currency' => 'GBP',
        ]);

        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/pos/checkouts/{$otherCheckout->id}")
            ->assertNotFound();
    }

    public function test_permission_gates(): void
    {
        $ctx = $this->seedTenantContext($this->posPermissions());

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/pos/checkouts', [
                'location_id' => $ctx['location']->id,
            ])
            ->assertForbidden();

        $checkoutId = $this->createCheckout($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/lines/service", [
                'description' => 'Perm test',
                'unit_price_cents' => 1000,
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/payments", [
                'tenders' => [['amount_cents' => 1000, 'payment_method_type' => 'cash']],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/pos/checkouts/{$checkoutId}/complete")
            ->assertOk();
    }
}
