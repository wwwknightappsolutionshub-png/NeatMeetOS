<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module4AAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function bookingPermissions(): array
    {
        return [
            'identity.view',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
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
            'name' => 'Test Cut',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Booking',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        return compact('service', 'client');
    }

    protected function appointmentPayload(
        array $ctx,
        string $clientId,
        string $serviceId,
        Carbon $startsAt,
        ?string $workspaceId = null,
    ): array {
        return [
            'client_id' => $clientId,
            'team_member_id' => $ctx['teamMember']->id,
            'location_id' => $ctx['location']->id,
            'workspace_id' => $workspaceId,
            'starts_at' => $startsAt->toDateTimeString(),
            'services' => [['booking_service_id' => $serviceId]],
        ];
    }

    public function test_booking_service_crud(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/booking-services', [
                'name' => 'Blow Dry',
                'description' => 'Finish blow-dry with styling product.',
                'image_url' => 'https://cdn.example.test/services/blow-dry.jpg',
                'duration_minutes' => 45,
                'base_price_cents' => 3500,
                'membership_price_cents' => 3000,
                'loyalty_price_cents' => 3150,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.membership_price_cents', 3000)
            ->assertJsonPath('data.loyalty_price_cents', 3150)
            ->assertJsonPath('data.description', 'Finish blow-dry with styling product.');
        $serviceId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/booking-services/'.$serviceId, [
                'name' => 'Blow Dry Pro',
                'membership_price_cents' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Blow Dry Pro')
            ->assertJsonPath('data.membership_price_cents', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'booking_service.created']);
    }

    public function test_appointment_create_and_cancel(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $this->appointmentPayload(
                $ctx,
                $fixtures['client']->id,
                $fixtures['service']->id,
                $startsAt,
                $ctx['workspace']->id,
            ));

        $create->assertCreated();
        $appointmentId = $create->json('data.id');

        $this->assertDatabaseHas('appointment_services', [
            'appointment_id' => $appointmentId,
            'service_name' => 'Test Cut',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/appointments/'.$appointmentId.'/cancel', [
                'cancellation_reason' => 'Client request',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CANCELLED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.cancelled']);
    }

    public function test_provider_double_booking_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0);
        $payload = $this->appointmentPayload(
            $ctx,
            $fixtures['client']->id,
            $fixtures['service']->id,
            $startsAt,
        );

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $payload)
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $payload)
            ->assertStatus(422);
    }

    public function test_workspace_double_booking_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $secondUser = User::factory()->create();

        $secondMember = TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $secondUser->id,
            'employment_type' => TeamMember::EMPLOYMENT_EMPLOYEE,
            'display_name' => 'Second Stylist',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        StaffProfile::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'team_member_id' => $secondMember->id,
            'is_bookable' => true,
        ]);

        $secondMember->operatingLocations()->sync([$ctx['location']->id]);
        $secondMember->workspaces()->sync([$ctx['workspace']->id]);

        StaffAvailabilityRule::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'team_member_id' => $secondMember->id,
            'location_id' => $ctx['location']->id,
            'workspace_id' => $ctx['workspace']->id,
            'day_of_week' => Carbon::now()->next(Carbon::MONDAY)->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_active' => true,
        ]);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(11, 0);

        $firstPayload = $this->appointmentPayload(
            $ctx,
            $fixtures['client']->id,
            $fixtures['service']->id,
            $startsAt,
            $ctx['workspace']->id,
        );

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $firstPayload)
            ->assertCreated();

        $secondPayload = $firstPayload;
        $secondPayload['team_member_id'] = $secondMember->id;

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $secondPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['workspace_id']);
    }

    public function test_outside_availability_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $startsAt = Carbon::now()->next(Carbon::MONDAY)->setTime(6, 0);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', $this->appointmentPayload(
                $ctx,
                $fixtures['client']->id,
                $fixtures['service']->id,
                $startsAt,
            ))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_tenant_isolation_on_appointments(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());

        $foreignClient = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Foreign',
            'is_active' => true,
        ]);

        $foreignAppt = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $foreignClient->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/appointments/'.$foreignAppt->id)
            ->assertNotFound();
    }

    public function test_viewer_cannot_create_appointment(): void
    {
        $ctx = $this->seedTenantContext($this->bookingPermissions());
        $fixtures = $this->seedBookableProvider($ctx);

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/appointments', $this->appointmentPayload(
                $ctx,
                $fixtures['client']->id,
                $fixtures['service']->id,
                Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0),
            ))
            ->assertForbidden();
    }
}
