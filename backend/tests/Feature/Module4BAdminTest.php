<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Crm\Models\Client;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module4BAdminTest extends TestCase
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
            'name' => 'Deposit Service',
            'duration_minutes' => 60,
            'deposit_required' => true,
            'deposit_amount_cents' => 2000,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Wait',
            'last_name' => 'List',
            'is_active' => true,
        ]);

        return compact('service', 'client');
    }

    public function test_service_deposit_metadata_and_appointment_snapshot(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', [
                'client_id' => $fixtures['client']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'starts_at' => $startsAt->toDateTimeString(),
                'services' => [['booking_service_id' => $fixtures['service']->id]],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.deposit_status', Appointment::DEPOSIT_PENDING)
            ->assertJsonPath('data.deposit_required_cents', 2000);

        $this->assertNotNull($create->json('data.booking_reference'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.created']);
    }

    public function test_recurring_series_creates_with_skip_reporting(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Quick Trim',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $firstStart = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', [
                'client_id' => $fixtures['client']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'starts_at' => $firstStart->copy()->addWeeks(1)->toDateTimeString(),
                'services' => [['booking_service_id' => $service->id]],
            ])
            ->assertCreated();

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/recurrence-series', [
                'client_id' => $fixtures['client']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'starts_at' => $firstStart->toDateTimeString(),
                'occurrence_count' => 3,
                'services' => [['booking_service_id' => $service->id]],
            ]);

        $response->assertCreated();
        $this->assertGreaterThanOrEqual(2, count($response->json('data.created_appointment_ids')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'recurrence_series.created']);
    }

    public function test_waitlist_crud_and_fulfilment(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/waitlist', [
                'client_id' => $fixtures['client']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'availability_notes' => 'Friday afternoon',
                'services' => [['booking_service_id' => $fixtures['service']->id]],
            ]);

        $create->assertCreated();
        $entryId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/waitlist/'.$entryId, ['status' => WaitlistEntry::STATUS_CONTACTED])
            ->assertOk();

        $startsAt = Carbon::now()->next(Carbon::FRIDAY)->setTime(14, 0);

        $fulfill = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/waitlist/'.$entryId.'/fulfill', [
                'starts_at' => $startsAt->toDateTimeString(),
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
            ]);

        $fulfill->assertCreated()
            ->assertJsonPath('data.waitlist.status', WaitlistEntry::STATUS_BOOKED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'waitlist_entry.fulfilled']);
    }

    public function test_deposit_status_waive(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', [
                'client_id' => $fixtures['client']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'starts_at' => Carbon::now()->next(Carbon::MONDAY)->setTime(12, 0)->toDateTimeString(),
                'services' => [['booking_service_id' => $fixtures['service']->id]],
            ]);

        $appointmentId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/appointments/'.$appointmentId.'/deposit-status', [
                'deposit_status' => Appointment::DEPOSIT_WAIVED,
            ])
            ->assertOk()
            ->assertJsonPath('data.deposit_status', Appointment::DEPOSIT_WAIVED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.deposit_status_updated']);
    }

    public function test_viewer_cannot_create_waitlist(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/waitlist', [
                'client_id' => $fixtures['client']->id,
                'location_id' => $ctx['location']->id,
            ])
            ->assertForbidden();
    }
}
