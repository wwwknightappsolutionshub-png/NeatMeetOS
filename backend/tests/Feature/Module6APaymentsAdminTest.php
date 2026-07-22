<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module6APaymentsAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function paymentsPermissions(): array
    {
        return [
            'booking.view',
            'booking.manage',
            'crm.view',
            'staff.view',
            'payments.view',
            'payments.manage',
            'payments.refund',
            'payments.reporting.view',
        ];
    }

    protected function seedDepositAppointment(array $ctx): Appointment
    {
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

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Deposit Colour',
            'duration_minutes' => 90,
            'deposit_required' => true,
            'deposit_amount_cents' => 2500,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Pay',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(14, 0);

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', [
                'client_id' => $client->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'starts_at' => $startsAt->toDateTimeString(),
                'services' => [['booking_service_id' => $service->id]],
            ]);

        $response->assertCreated();

        return Appointment::query()->findOrFail($response->json('data.id'));
    }

    public function test_manual_payment_transaction_creation(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/manual', [
                'transaction_type' => PaymentTransactionType::SALE,
                'amount_cents' => 5000,
                'payment_method_type' => 'cash',
                'payment_method_label' => 'Front desk cash',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', PaymentTransactionStatus::SUCCEEDED)
            ->assertJsonPath('data.amount_cents', 5000);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.created.manual']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'payment.recorded']);
    }

    public function test_deposit_payment_recording_syncs_appointment_and_commerce_record(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());
        $appointment = $this->seedDepositAppointment($ctx);

        $this->assertSame(Appointment::DEPOSIT_PENDING, $appointment->deposit_status);

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$appointment->id}/deposit/pay", [
                'payment_method_type' => 'card',
                'payment_method_label' => 'Manual card',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.appointment.deposit_status', Appointment::DEPOSIT_SATISFIED);

        $this->assertDatabaseHas('commerce_deposit_records', [
            'appointment_id' => $appointment->id,
            'lifecycle_state' => 'collected',
            'collected_cents' => 2500,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.deposit_recorded']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'deposit.collected']);
    }

    public function test_deposit_waiver_flow(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());
        $appointment = $this->seedDepositAppointment($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$appointment->id}/deposit/waive", [
                'notes' => 'VIP waiver',
            ])
            ->assertOk()
            ->assertJsonPath('data.appointment.deposit_status', Appointment::DEPOSIT_WAIVED);

        $this->assertDatabaseHas('commerce_deposit_records', [
            'appointment_id' => $appointment->id,
            'lifecycle_state' => 'waived',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.deposit_waived']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'deposit.waived']);
    }

    public function test_deposit_refund_flow(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());
        $appointment = $this->seedDepositAppointment($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$appointment->id}/deposit/pay", [
                'payment_method_type' => 'cash',
            ])
            ->assertCreated();

        $refund = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$appointment->id}/deposit/refund", [
                'reason' => 'Client cancelled early',
            ]);

        $refund->assertCreated();

        $this->assertDatabaseHas('commerce_deposit_records', [
            'appointment_id' => $appointment->id,
            'lifecycle_state' => 'refunded',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refund_created']);
        $this->assertDatabaseHas('commerce_events', ['event_name' => 'deposit.refunded']);
    }

    public function test_allocation_validation_rejects_over_allocation(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/manual', [
                'transaction_type' => PaymentTransactionType::SALE,
                'amount_cents' => 1000,
                'allocations' => [
                    ['allocation_type' => 'checkout_sale', 'amount_cents' => 1500],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_mark_payment_failed(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/payment-link', [
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'amount_cents' => 3000,
            ])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/payments/{$id}/mark-failed", [
                'failure_code' => 'expired',
                'failure_message' => 'Link expired',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::FAILED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.status_updated']);
    }

    public function test_payments_list_and_detail_are_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());

        $txn = PaymentTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'transaction_type' => PaymentTransactionType::SALE,
            'direction' => 'inbound',
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount_cents' => 999,
            'currency' => 'GBP',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonMissing(['id' => $txn->id]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/payments/{$txn->id}")
            ->assertNotFound();
    }

    public function test_view_only_permission_cannot_manage_or_report(): void
    {
        $viewOnly = $this->seedTenantContext(['payments.view']);

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/payments')
            ->assertOk();

        $this->withTenantAuth($viewOnly['token'])
            ->postJson('/api/v1/admin/payments/manual', [
                'transaction_type' => PaymentTransactionType::SALE,
                'amount_cents' => 100,
            ])
            ->assertForbidden();

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/payments/summary')
            ->assertForbidden();
    }

    public function test_manage_without_refund_cannot_create_refund(): void
    {
        $ctx = $this->seedTenantContext([
            'payments.view',
            'payments.manage',
            'booking.view',
            'booking.manage',
            'crm.view',
            'staff.view',
        ]);

        $appointment = $this->seedDepositAppointment($ctx);

        $pay = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$appointment->id}/deposit/pay")
            ->assertCreated();

        $paymentId = $pay->json('data.payment_transaction.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/payments/{$paymentId}/refunds")
            ->assertForbidden();
    }

    public function test_reporting_permission_can_access_summary(): void
    {
        $ctx = $this->seedTenantContext(['payments.view', 'payments.reporting.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/payments/summary')
            ->assertOk();
    }

    public function test_deposit_inspect_endpoint_returns_contract_bridge(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());
        $appointment = $this->seedDepositAppointment($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/appointments/{$appointment->id}/deposit")
            ->assertOk()
            ->assertJsonPath('data.appointment.deposit_status', Appointment::DEPOSIT_PENDING)
            ->assertJsonStructure([
                'data' => ['appointment', 'deposit_contract', 'deposit_record'],
            ]);
    }

    public function test_refundable_balance_rules(): void
    {
        $ctx = $this->seedTenantContext($this->paymentsPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/manual', [
                'transaction_type' => PaymentTransactionType::SALE,
                'amount_cents' => 2000,
            ])
            ->assertCreated();

        $paymentId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/payments/{$paymentId}/refunds", [
                'amount_cents' => 2500,
            ])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/payments/{$paymentId}/refunds", [
                'amount_cents' => 1000,
                'reason' => 'Partial refund',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('payment_refunds', [
            'payment_transaction_id' => $paymentId,
            'amount_cents' => 1000,
        ]);
    }
}
