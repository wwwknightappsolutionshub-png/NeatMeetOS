<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\AuditLog;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module4CAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function bookingPermissions(): array
    {
        return [
            'crm.view',
            'staff.view',
            'booking.view',
            'booking.manage',
        ];
    }

    protected function seedBookableProvider(array $ctx): array
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
            'name' => 'Walk-in Cut',
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Walk',
            'last_name' => 'In',
            'is_active' => true,
        ]);

        return compact('service', 'client');
    }

    public function test_walk_in_waiting_and_seat_lifecycle(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/walk-ins', [
                'client_id' => $fixtures['client']->id,
                'location_id' => $ctx['location']->id,
                'services' => [['booking_service_id' => $fixtures['service']->id]],
                'seat_immediately' => false,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.booking_source', 'walk_in')
            ->assertJsonPath('data.walk_in_stage', 'waiting')
            ->assertJsonPath('data.status', 'pending');

        $walkInId = $create->json('data.id');
        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $seat = $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/walk-ins/{$walkInId}/seat", [
                'team_member_id' => $ctx['teamMember']->id,
                'workspace_id' => $ctx['workspace']->id,
                'starts_at' => $startsAt->toIso8601String(),
            ]);

        $seat->assertOk()
            ->assertJsonPath('data.walk_in_stage', 'seated')
            ->assertJsonPath('data.status', 'checked_in');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $ctx['tenant']->id,
            'action' => 'walk_in.seated',
        ]);
    }

    public function test_no_show_and_lifecycle_restrictions(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $fixtures['client']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(45),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-NOSHOW1',
        ]);

        $noShow = $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/appointments/{$appointment->id}/status", [
                'status' => 'no_show',
                'no_show_reason' => 'Client did not arrive',
            ]);

        $noShow->assertOk()
            ->assertJsonPath('data.status', 'no_show')
            ->assertJsonPath('data.no_show_reason', 'Client did not arrive');

        $invalid = $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/appointments/{$appointment->id}/status", [
                'status' => 'checked_in',
            ]);

        $invalid->assertStatus(422);

        $correct = $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/appointments/{$appointment->id}/correct-status", [
                'status' => 'confirmed',
                'correction_note' => 'Client called — rescheduled same day',
            ]);

        $correct->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_rebook_from_appointment(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::TUESDAY)->setTime(14, 0);

        $source = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $fixtures['client']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(45),
            'status' => Appointment::STATUS_NO_SHOW,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-REBOOKSRC',
        ]);

        \App\Domains\Booking\Models\AppointmentServiceLine::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => $source->id,
            'booking_service_id' => $fixtures['service']->id,
            'service_name' => $fixtures['service']->name,
            'duration_minutes' => 45,
            'sort_order' => 0,
        ]);

        $newStart = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(11, 0);

        $rebook = $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/appointments/{$source->id}/rebook", [
                'starts_at' => $newStart->toIso8601String(),
            ]);

        $rebook->assertCreated()
            ->assertJsonPath('data.client_id', $fixtures['client']->id)
            ->assertJsonPath('data.rebooked_from_appointment_id', $source->id);
    }

    public function test_waitlist_operational_filters_and_contact(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $entry = WaitlistEntry::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $fixtures['client']->id,
            'location_id' => $ctx['location']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'preferred_starts_at' => Carbon::now()->addDays(3),
            'status' => WaitlistEntry::STATUS_WAITING,
        ]);

        $entry->bookableServices()->attach($fixtures['service']->id, [
            'service_name' => $fixtures['service']->name,
            'sort_order' => 0,
        ]);

        $list = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/waitlist?status=waiting&team_member_id='.$ctx['teamMember']->id);

        $list->assertOk()->assertJsonCount(1, 'data');

        $update = $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/waitlist/{$entry->id}", [
                'status' => 'contacted',
            ]);

        $update->assertOk()
            ->assertJsonPath('data.status', 'contacted');

        $this->assertNotNull($update->json('data.contacted_at'));
    }

    public function test_workspace_reassignment_validation(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::THURSDAY)->setTime(9, 0);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $fixtures['client']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(45),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-WSREASGN',
        ]);

        $reassign = $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/appointments/{$appointment->id}/workspace", [
                'workspace_id' => $ctx['workspace']->id,
            ]);

        $reassign->assertOk()->assertJsonPath('data.workspace_id', $ctx['workspace']->id);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $ctx['tenant']->id,
            'action' => 'appointment.workspace_reassigned',
        ]);
    }

    public function test_booking_board_day_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $date = Carbon::now()->next(Carbon::FRIDAY)->setTime(10, 0);

        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $fixtures['client']->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $date,
            'ends_at' => $date->copy()->addMinutes(45),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-BOARD01',
        ]);

        $board = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/booking-board/day?date='.$date->toDateString());

        $board->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonCount(1, 'data.appointments');
    }

    public function test_viewer_cannot_create_walk_in(): void
    {
        $ctx = $this->seedTenantContext(['booking.view', 'crm.view', 'staff.view']);
        $fixtures = $this->seedBookableProvider($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/walk-ins', [
                'client_id' => $fixtures['client']->id,
                'location_id' => $ctx['location']->id,
                'services' => [['booking_service_id' => $fixtures['service']->id]],
            ])
            ->assertForbidden();
    }
}
