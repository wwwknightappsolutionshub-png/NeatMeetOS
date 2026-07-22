<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module1LocationOpeningHoursTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_location_opening_hours_can_be_updated(): void
    {
        $ctx = $this->seedTenantContext(['identity.view', 'identity.manage']);

        $hours = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_closed' => false],
            ['day_of_week' => 7, 'start_time' => null, 'end_time' => null, 'is_closed' => true],
        ];

        $this->withToken($ctx['token'])
            ->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->putJson('/api/v1/admin/locations/'.$ctx['location']->id, [
                'opening_hours' => $hours,
            ])
            ->assertOk()
            ->assertJsonPath('data.opening_hours.0.day_of_week', 1)
            ->assertJsonPath('data.opening_hours.1.is_closed', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'location.updated']);
    }

    public function test_online_slots_empty_when_location_closed(): void
    {
        $ctx = $this->seedTenantContext([
            'booking.view', 'booking.manage', 'crm.view', 'crm.manage', 'staff.view', 'staff.manage', 'identity.manage',
        ]);

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
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ]);
        }

        $service = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Cut',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
        ]);

        $ctx['location']->opening_hours = [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_closed' => false],
            ['day_of_week' => 7, 'start_time' => null, 'end_time' => null, 'is_closed' => true],
        ];
        $ctx['location']->save();

        $sunday = Carbon::now()->next(Carbon::SUNDAY)->toDateString();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $service->id,
                'location_id' => $ctx['location']->id,
                'date' => $sunday,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data.slots');
    }

    public function test_viewer_cannot_update_opening_hours(): void
    {
        $ctx = $this->seedTenantContext(['identity.view', 'identity.manage']);

        $this->withToken($ctx['viewerToken'])
            ->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->putJson('/api/v1/admin/locations/'.$ctx['location']->id, [
                'opening_hours' => [
                    ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'is_closed' => false],
                ],
            ])
            ->assertForbidden();
    }
}
