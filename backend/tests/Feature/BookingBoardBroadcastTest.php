<?php

namespace Tests\Feature;

use App\Domains\Booking\Events\BookingBoardUpdated;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class BookingBoardBroadcastTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_creating_appointment_dispatches_booking_board_updated(): void
    {
        Event::fake([BookingBoardUpdated::class]);

        $ctx = $this->seedTenantContext([
            'identity.view',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
            'booking.view',
            'booking.manage',
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

        $starts = Carbon::now()->next('Tuesday')->setTime(10, 0);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/appointments', [
                'client_id' => $client->id,
                'team_member_id' => $ctx['teamMember']->id,
                'location_id' => $ctx['location']->id,
                'workspace_id' => $ctx['workspace']->id,
                'starts_at' => $starts->toDateTimeString(),
                'services' => [['booking_service_id' => $service->id]],
            ])
            ->assertCreated();

        Event::assertDispatched(BookingBoardUpdated::class, function (BookingBoardUpdated $event) use ($ctx, $starts) {
            return $event->tenantId === (string) $ctx['tenant']->id
                && $event->date === $starts->toDateString()
                && $event->locationId === (string) $ctx['location']->id;
        });
    }

    public function test_booking_board_private_channel_auth_for_team_member(): void
    {
        $ctx = $this->seedTenantContext(['booking.view']);
        $tenantId = (string) $ctx['tenant']->id;

        config(['broadcasting.default' => 'null']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-tenant.'.$tenantId.'.booking-board',
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }
}
