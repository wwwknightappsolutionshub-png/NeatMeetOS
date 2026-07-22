<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Commerce\Assemblers\CheckoutTotalsCalculator;
use App\Shared\Commerce\Assemblers\DepositLifecycleMapper;
use App\Shared\Commerce\Assemblers\InventoryConsumptionRequestBuilder;
use App\Shared\Commerce\Contracts\CheckoutImportFromBookingContract;
use App\Shared\Commerce\DTO\CheckoutLineDto;
use App\Shared\Commerce\DTO\PaymentAllocationDto;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Enums\EntitlementReferenceState;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Commerce\Models\CommerceEvent;
use App\Shared\Commerce\Services\AppointmentCheckoutEligibilityValidator;
use App\Shared\Commerce\Services\PaymentAllocationValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class CommerceContractTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function seedAppointmentContext(array $ctx): void
    {
        if (StaffProfile::withoutGlobalScopes()->where('team_member_id', $ctx['teamMember']->id)->exists()) {
            return;
        }

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

    protected function seedAppointment(array $ctx, array $overrides = []): Appointment
    {
        $this->seedAppointmentContext($ctx);

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Commerce Cut',
            'duration_minutes' => 60,
            'base_price_cents' => 5000,
            'deposit_required' => true,
            'deposit_amount_cents' => 1500,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Commerce',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(11, 0);

        $appointment = Appointment::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(60),
            'status' => Appointment::STATUS_CHECKED_IN,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-COMMERCE'.substr(uniqid(), -6),
            'deposit_status' => Appointment::DEPOSIT_PENDING,
            'deposit_required_cents' => 1500,
            'deposit_rule_snapshot' => ['services' => [['deposit_amount_cents' => 1500]]],
            'billing_settlement_status' => BillingSettlementStatus::UNSETTLED,
        ], $overrides));

        AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $appointment->id,
            'booking_service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => 60,
            'price_cents' => 5000,
            'sort_order' => 0,
            'package_entitlement_id' => '00000000-0000-4000-8000-000000000099',
            'entitlement_source' => 'package',
        ]);

        return $appointment;
    }

    public function test_deposit_snapshot_maps_to_lifecycle_contract(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $appointment = $this->seedAppointment($ctx);

        $mapper = app(DepositLifecycleMapper::class);
        $contract = $mapper->resolveForAppointment($appointment->id);

        $this->assertSame(Appointment::DEPOSIT_PENDING, $contract->bookingDepositStatus);
        $this->assertSame(DepositLifecycleState::REQUIRED, $contract->lifecycleState);
        $this->assertSame(1500, $contract->requiredCents);
    }

    public function test_appointment_import_produces_checkout_lines_and_entitlement_reference(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $appointment = $this->seedAppointment($ctx);

        $import = app(CheckoutImportFromBookingContract::class)->import($appointment);

        $this->assertTrue($import->checkoutEligible);
        $this->assertCount(1, $import->lines);
        $this->assertSame(SaleLineType::APPOINTMENT_SERVICE, $import->lines[0]->lineType);
        $this->assertSame(5000, $import->lines[0]->lineTotalCents);
        $this->assertCount(1, $import->entitlementReferences);
        $this->assertSame(EntitlementReferenceState::REFERENCED, $import->entitlementReferences[0]->state);
        $this->assertSame('package', $import->entitlementReferences[0]->entitlementSource);
    }

    public function test_checkout_eligibility_rejects_cancelled_and_no_show(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $validator = app(AppointmentCheckoutEligibilityValidator::class);

        $cancelled = $this->seedAppointment($ctx, ['status' => Appointment::STATUS_CANCELLED]);
        $this->assertFalse($validator->validate($cancelled)['eligible']);

        $noShow = $this->seedAppointment($ctx, ['status' => Appointment::STATUS_NO_SHOW]);
        $this->assertFalse($validator->validate($noShow)['eligible']);
    }

    public function test_checkout_totals_calculator_applies_discount_and_deposit_credit(): void
    {
        $calculator = app(CheckoutTotalsCalculator::class);

        $totals = $calculator->calculate([
            new CheckoutLineDto(SaleLineType::APPOINTMENT_SERVICE, 'Cut', 1, 5000, 5000),
            new CheckoutLineDto(SaleLineType::DISCOUNT, 'Promo', 1, -500, -500),
            new CheckoutLineDto(SaleLineType::DEPOSIT_CREDIT, 'Deposit', 1, -1500, -1500),
        ], taxCents: 900);

        $this->assertSame(5000, $totals->subtotalCents);
        $this->assertSame(500, $totals->discountCents);
        $this->assertSame(1500, $totals->depositCreditCents);
        $this->assertSame(3900, $totals->totalCents);
    }

    public function test_payment_allocation_validator_enforces_sum(): void
    {
        $validator = app(PaymentAllocationValidator::class);

        $validator->validateAllocations(3000, [
            new PaymentAllocationDto(PaymentAllocationType::CHECKOUT_SALE, 2000, 'commerce_checkout', 'checkout-1'),
            new PaymentAllocationDto(PaymentAllocationType::DEPOSIT, 1000, 'commerce_deposit_record', 'dep-1'),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $validator->validateAllocations(3000, [
            new PaymentAllocationDto(PaymentAllocationType::CHECKOUT_SALE, 1000, 'commerce_checkout', 'checkout-1'),
        ]);
    }

    public function test_inventory_consumption_builder_from_retail_line(): void
    {
        $builder = app(InventoryConsumptionRequestBuilder::class);

        $requests = $builder->buildFromCheckoutLines('checkout-1', 'loc-1', [
            [
                'line_type' => SaleLineType::RETAIL_PRODUCT,
                'checkout_line_id' => 'line-1',
                'reference_id' => 'prod-1',
                'pricing_snapshot' => [],
                'quantity' => 2,
            ],
        ]);

        $this->assertCount(1, $requests);
        $this->assertSame('prod-1', $requests[0]->productId);
        $this->assertSame('2', $requests[0]->quantity);
    }

    public function test_checkout_import_endpoint_and_deposit_event(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $appointment = $this->seedAppointment($ctx);

        $response = $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/appointments/{$appointment->id}/checkout-import");

        $response->assertOk()
            ->assertJsonPath('data.checkout_eligible', true)
            ->assertJsonPath('data.deposit.lifecycle_state', DepositLifecycleState::REQUIRED);

        $this->assertDatabaseHas('commerce_events', [
            'tenant_id' => $ctx['tenant']->id,
            'event_name' => 'deposit.required',
            'aggregate_id' => $appointment->id,
        ]);
    }

    public function test_collected_deposit_record_overrides_lifecycle_mapping(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $appointment = $this->seedAppointment($ctx);

        CommerceDepositRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $appointment->id,
            'booking_deposit_status' => Appointment::DEPOSIT_PENDING,
            'required_cents' => 1500,
            'collected_cents' => 1500,
            'lifecycle_state' => DepositLifecycleState::COLLECTED,
        ]);

        $contract = app(DepositLifecycleMapper::class)->resolveForAppointment($appointment->id);
        $this->assertSame(DepositLifecycleState::COLLECTED, $contract->lifecycleState);
        $this->assertSame(1500, $contract->collectedCents);
    }
}
