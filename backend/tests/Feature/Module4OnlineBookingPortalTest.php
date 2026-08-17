<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module4OnlineBookingPortalTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function seedOnlineBookingContext(): array
    {
        $ctx = $this->seedTenantContext([
            'booking.view',
            'booking.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
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
            'name' => 'Online Cut',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
        ]);

        $offline = BookableService::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Staff Only',
            'duration_minutes' => 30,
            'is_active' => true,
            'is_bookable_online' => false,
        ]);

        return array_merge($ctx, compact('service', 'offline'));
    }

    public function test_catalog_requires_tenant_header(): void
    {
        $this->getJson('/api/v1/book/catalog')
            ->assertStatus(400);
    }

    public function test_catalog_returns_online_services_only(): void
    {
        $ctx = $this->seedOnlineBookingContext();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', $ctx['tenant']->slug)
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $ctx['service']->id)
            ->assertJsonPath('data.locations.0.id', $ctx['location']->id)
            ->assertJsonStructure([
                'data' => [
                    'locations' => [
                        ['id', 'name', 'address', 'contact_phone'],
                    ],
                    'tenant' => [
                        'branding' => [
                            'store_status',
                            'hero_emblem_mode',
                        ],
                    ],
                ],
            ]);
    }

    public function test_slots_and_book_appointment_online(): void
    {
        $ctx = $this->seedOnlineBookingContext();
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);

        $created = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'workspace_id' => $slots[0]['workspace_id'] ?? $ctx['workspace']->id,
                'starts_at' => $slots[0]['starts_at'],
                'first_name' => 'Online',
                'last_name' => 'Guest',
                'email' => 'online.guest@example.com',
                'phone' => '+447700900123',
                'client_notes' => 'First visit',
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_source', Appointment::SOURCE_ONLINE)
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED);

        $this->assertNotEmpty($created->json('data.public_manage_token'));
        $this->assertNotEmpty($created->json('data.manage_path'));
        $this->assertNotEmpty($created->json('data.booking_reference'));

        $this->assertDatabaseHas('notifications_messages', [
            'appointment_id' => $created->json('data.id'),
            'purpose' => NotificationPurpose::BOOKING_CONFIRMATION,
        ]);
        $this->assertDatabaseHas('notifications_messages', [
            'appointment_id' => $created->json('data.id'),
            'purpose' => NotificationPurpose::INTERNAL_NOTE_DELIVERY,
        ]);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'email' => 'online.guest@example.com',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $created->json('data.id'),
            'booking_source' => Appointment::SOURCE_ONLINE,
            'tenant_id' => $ctx['tenant']->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created']);
    }

    public function test_slots_heal_when_availability_exists_but_profile_not_bookable(): void
    {
        $ctx = $this->seedOnlineBookingContext();

        StaffProfile::withoutGlobalScopes()
            ->where('team_member_id', $ctx['teamMember']->id)
            ->update([
                'is_bookable' => false,
                'show_in_online_booking' => false,
            ]);

        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);
        $this->assertDatabaseHas('staff_profiles', [
            'team_member_id' => $ctx['teamMember']->id,
            'is_bookable' => true,
            'show_in_online_booking' => true,
        ]);
    }

    public function test_manage_and_cancel_booking_with_token(): void
    {
        $ctx = $this->seedOnlineBookingContext();
        $date = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);

        $created = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => $slots[0]['starts_at'],
                'first_name' => 'Manage',
                'last_name' => 'Me',
                'email' => 'manage.me@example.com',
                'phone' => '+447700900321',
            ])
            ->assertCreated();

        $reference = $created->json('data.booking_reference');
        $token = $created->json('data.public_manage_token');

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/appointments/'.$reference.'?token=wrong')
            ->assertStatus(422);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/appointments/'.$reference.'?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.booking_reference', $reference);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments/'.$reference.'/cancel', [
                'token' => $token,
                'reason' => 'Plans changed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'cancel')
            ->assertJsonPath('data.status', 'pending');

        $changeRequestId = null;

        $pending = \App\Domains\Booking\Models\BookingChangeRequest::withoutGlobalScopes()
            ->where('appointment_id', $created->json('data.id'))
            ->first();
        $this->assertNotNull($pending);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments/'.$reference.'/cancel', [
                'token' => $token,
            ])
            ->assertStatus(422);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/change-requests/resolve', [
                'id' => $pending->id,
                'token' => $pending->action_token,
                'action' => 'accept',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('appointments', [
            'id' => $created->json('data.id'),
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('notifications_messages', [
            'appointment_id' => $created->json('data.id'),
            'purpose' => NotificationPurpose::BOOKING_CANCELLATION,
        ]);
    }

    public function test_booking_reminder_command_dispatches_once(): void
    {
        $ctx = $this->seedOnlineBookingContext();
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Soon',
            'last_name' => 'Guest',
            'email' => 'soon.guest@example.com',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $starts = now()->addMinutes(45);
        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'workspace_id' => $ctx['workspace']->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-REMINDER1',
            'public_manage_token' => 'token-reminder-test-abcdefghijklmnopqrst',
        ]);

        Artisan::call('notifications:dispatch-booking-reminders', ['--window' => 10]);
        $afterFirst = \App\Domains\Notifications\Models\NotificationMessage::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->where('purpose', NotificationPurpose::BOOKING_REMINDER)
            ->count();
        $this->assertGreaterThanOrEqual(1, $afterFirst);

        Artisan::call('notifications:dispatch-booking-reminders', ['--window' => 10]);
        $afterSecond = \App\Domains\Notifications\Models\NotificationMessage::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->where('purpose', NotificationPurpose::BOOKING_REMINDER)
            ->count();

        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_book_reuses_existing_client_by_phone(): void
    {
        $ctx = $this->seedOnlineBookingContext();

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Existing',
            'last_name' => 'Client',
            'email' => 'reuse@example.com',
            'phone' => '+447700900555',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $date = Carbon::now()->next(Carbon::TUESDAY)->toDateString();
        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => $slots[0]['starts_at'],
                'phone' => '+44 7700 900555',
            ])
            ->assertCreated();

        $this->assertSame(1, Client::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('phone_normalized', '+447700900555')
            ->count());
    }

    public function test_guest_contact_lookup_returns_presence_flags_without_pii(): void
    {
        $ctx = $this->seedOnlineBookingContext();

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Existing',
            'last_name' => 'Guest',
            'email' => 'reuse@example.com',
            'phone' => '+447700900555',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/guest-contact?phone='.urlencode('+44 7700 900555'))
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.has_name', true)
            ->assertJsonPath('data.has_email', true)
            ->assertJsonMissingPath('data.first_name')
            ->assertJsonMissingPath('data.email');

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/guest-contact?phone='.urlencode('+447700900000'))
            ->assertOk()
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.has_name', false);
    }

    public function test_book_allows_optional_name_and_requires_phone(): void
    {
        $ctx = $this->seedOnlineBookingContext();
        $date = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();

        $slots = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/slots?'.http_build_query([
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'date' => $date,
                'team_member_id' => $ctx['teamMember']->id,
            ]))
            ->assertOk()
            ->json('data.slots');

        $this->assertNotEmpty($slots);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => $slots[0]['starts_at'],
                'email' => 'noname@example.com',
            ])
            ->assertStatus(422);

        $created = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => $slots[0]['starts_at'],
                'phone' => '+447700900888',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'phone' => '+447700900888',
            'phone_normalized' => '+447700900888',
            'first_name' => null,
        ]);
        $this->assertNotEmpty($created->json('data.id'));
    }

    public function test_online_booking_is_tenant_isolated(): void
    {
        $ctx = $this->seedOnlineBookingContext();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['otherTenant']->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonCount(0, 'data.services');

        $this->withHeaders(['X-Tenant-Slug' => $ctx['otherTenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                'first_name' => 'X',
                'last_name' => 'Y',
                'email' => 'x@example.com',
                'phone' => '+447700900000',
            ])
            ->assertNotFound();
    }
}
