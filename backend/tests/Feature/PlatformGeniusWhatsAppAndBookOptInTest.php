<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformGeniusWhatsAppAndBookOptInTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /** @return array<string, mixed> */
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
            'name' => 'WhatsApp Cut',
            'duration_minutes' => 60,
            'is_active' => true,
            'is_bookable_online' => true,
            'base_price_cents' => 4000,
        ]);

        return array_merge($ctx, compact('service'));
    }

    protected function actingAsPlatformOwner(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Owner',
            'email' => 'platform.wa@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_platform_admin_can_configure_genius_whatsapp(): void
    {
        $this->actingAsPlatformOwner();

        $this->getJson('/api/v1/platform/whatsapp-settings')
            ->assertOk()
            ->assertJsonPath('data.whatsapp.provider', 'genius');

        Http::fake([
            'restapi.geniusdevel.com/*' => Http::response(['ok' => true], 200),
        ]);

        $this->putJson('/api/v1/platform/whatsapp-settings', [
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-test-key',
            'session_id' => 'session_test_neatmeet',
            'base_url' => 'https://restapi.geniusdevel.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp.enabled', true)
            ->assertJsonPath('data.whatsapp.has_api_key', true)
            ->assertJsonPath('data.whatsapp.configured', true);

        $this->postJson('/api/v1/platform/whatsapp-settings/test', [
            'phone' => '+447700900999',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }

    public function test_book_whatsapp_opt_in_enables_preference_and_uses_whatsapp_channel(): void
    {
        $ctx = $this->seedBookingCtx();
        app(TenantContext::class)->set($ctx['tenant']);

        PlatformWhatsAppSettings::query()->create([
            'enabled' => true,
            'provider' => 'genius',
            'api_key' => 'api-test-key',
            'session_id' => 'session_test_neatmeet',
            'base_url' => 'https://restapi.geniusdevel.com',
        ]);

        Http::fake([
            'restapi.geniusdevel.com/*' => Http::response(['ok' => true], 200),
        ]);

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
                'first_name' => 'Wa',
                'last_name' => 'Guest',
                'email' => 'wa.guest@example.test',
                'phone' => '+447700900777',
                'whatsapp_opt_in' => true,
            ])
            ->assertCreated();

        $client = Client::withoutGlobalScopes()->where('email', 'wa.guest@example.test')->first();
        $this->assertNotNull($client);

        $pref = NotificationPreference::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->first();
        $this->assertNotNull($pref);
        $this->assertTrue((bool) $pref->allow_whatsapp);

        $message = NotificationMessage::withoutGlobalScopes()
            ->where('appointment_id', $created->json('data.id'))
            ->where('purpose', NotificationPurpose::BOOKING_CONFIRMATION)
            ->where('channel', NotificationChannel::WHATSAPP)
            ->first();

        $this->assertNotNull($message);
        $this->assertTrue(app(PlatformWhatsAppSettingsService::class)->isGeniusReady());
    }
}
