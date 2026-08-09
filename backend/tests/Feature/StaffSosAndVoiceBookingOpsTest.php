<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\StaffSosAlert;
use App\Domains\Booking\Services\StaffSosAlertService;
use App\Domains\Crm\Models\Client;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class StaffSosAndVoiceBookingOpsTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function seedBookingCtx(): array
    {
        $ctx = $this->seedTenantContext([
            'booking.view',
            'booking.manage',
            'crm.view',
            'crm.manage',
            'staff.view',
            'staff.manage',
            'notifications.view',
            'notifications.manage',
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
            'name' => 'SOS Cut',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
            'base_price_cents' => 3500,
        ]);

        return array_merge($ctx, compact('service'));
    }

    public function test_online_booking_raises_staff_sos_alert(): void
    {
        $ctx = $this->seedBookingCtx();
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

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/book/appointments', [
                'booking_service_id' => $ctx['service']->id,
                'location_id' => $ctx['location']->id,
                'team_member_id' => $ctx['teamMember']->id,
                'workspace_id' => $slots[0]['workspace_id'] ?? $ctx['workspace']->id,
                'starts_at' => $slots[0]['starts_at'],
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada.sos@example.test',
                'phone' => '+447700900111',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('staff_sos_alerts', [
            'tenant_id' => $ctx['tenant']->id,
            'kind' => StaffSosAlert::KIND_NEW_BOOKING,
            'status' => StaffSosAlert::STATUS_ACTIVE,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/staff-sos-alerts')
            ->assertOk()
            ->assertJsonPath('data.items.0.kind', StaffSosAlert::KIND_NEW_BOOKING);
    }

    public function test_catalog_exposes_owner_whatsapp(): void
    {
        $ctx = $this->seedBookingCtx();
        $ctx['tenant']->owner_whatsapp = '+447700900999';
        $ctx['tenant']->save();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.tenant.owner_whatsapp', '+447700900999');
    }

    public function test_approaching_sos_shift_notifies_whatsapp_when_opted_in(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00')); // Monday mid-morning
        $ctx = $this->seedBookingCtx();
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Pat',
            'last_name' => 'Client',
            'email' => 'pat.shift@example.test',
            'phone' => '+447700900222',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        NotificationPreference::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'allow_email' => true,
            'allow_sms' => true,
            'allow_whatsapp' => true,
            'allow_push' => false,
            'booking_notifications' => true,
            'payment_notifications' => true,
            'membership_notifications' => true,
            'general_notifications' => true,
            'preferred_channel' => NotificationChannel::WHATSAPP,
        ]);

        $starts = now()->addMinutes(20);
        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'workspace_id' => $ctx['workspace']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-SOSSHIFT1',
            'public_manage_token' => 'token-sos-shift-1',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        $sos = app(StaffSosAlertService::class);
        $alert = $sos->raiseApproaching($appointment);
        $this->assertNotNull($alert);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/staff-sos-alerts/'.$alert->id.'/shift', [
                'minutes' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('data.alert.status', StaffSosAlert::STATUS_RESOLVED);

        $appointment->refresh();
        $this->assertTrue($appointment->starts_at->equalTo($starts->copy()->addMinutes(20)));

        $message = NotificationMessage::withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->where('purpose', NotificationPurpose::BOOKING_RESCHEDULE)
            ->first();

        $this->assertNotNull($message);
        $this->assertSame(NotificationChannel::WHATSAPP, $message->channel);
        $this->assertStringContainsString('20 minutes', (string) $message->body_text);

        Carbon::setTestNow();
    }

    public function test_dispatch_approaching_sos_command_raises_within_window(): void
    {
        $ctx = $this->seedBookingCtx();
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Soon',
            'last_name' => 'Client',
            'email' => 'soon@example.test',
            'phone' => '+447700900333',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        $starts = now()->addMinutes(20);
        Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'workspace_id' => $ctx['workspace']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'booking_reference' => 'NM-SOON20',
            'public_manage_token' => 'token-soon-20',
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ]);

        Artisan::call('booking:dispatch-approaching-sos', ['--lead' => 20, '--window' => 2]);

        $this->assertDatabaseHas('staff_sos_alerts', [
            'tenant_id' => $ctx['tenant']->id,
            'kind' => StaffSosAlert::KIND_APPROACHING,
            'status' => StaffSosAlert::STATUS_ACTIVE,
        ]);
    }

    public function test_acknowledge_stops_active_sos(): void
    {
        $ctx = $this->seedBookingCtx();
        app(TenantContext::class)->set($ctx['tenant']);

        $alert = StaffSosAlert::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'appointment_id' => null,
            'kind' => StaffSosAlert::KIND_NEW_BOOKING,
            'status' => StaffSosAlert::STATUS_ACTIVE,
            'title' => 'SOS · Test',
            'body' => 'Test body',
            'payload_json' => ['allow_shift' => false],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/staff-sos-alerts/'.$alert->id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status', StaffSosAlert::STATUS_ACKNOWLEDGED);
    }
}
