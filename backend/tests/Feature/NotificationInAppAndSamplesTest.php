<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Services\NotificationStarterTemplateService;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class NotificationInAppAndSamplesTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'notifications.view',
            'notifications.manage',
            'notifications.dispatch',
            'crm.view',
            'crm.manage',
            'booking.view',
            'booking.manage',
        ];
    }

    public function test_install_sample_templates_includes_email_and_in_app(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $first = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/templates/install-samples')
            ->assertOk();

        $created = (int) $first->json('data.created');
        $this->assertSame(22, $created);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/templates/install-samples')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 22);

        $list = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/templates?channel=in_app')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($list);
        $this->assertTrue(collect($list)->every(fn ($row) => $row['channel'] === NotificationChannel::IN_APP));
    }

    public function test_booking_confirmation_fans_out_email_and_in_app_notice(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        app(NotificationStarterTemplateService::class)->installSamples();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Ops',
            'last_name' => 'Client',
            'email' => 'ops.inapp@example.test',
            'phone' => '+447700901111',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        ClientConsentRecord::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'consent_type' => ClientConsentRecord::TYPE_MARKETING_EMAIL,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
            'recorded_at' => now(),
        ]);

        // Sync operational prefs so email is allowed.
        app(\App\Domains\Notifications\Services\NotificationPreferenceService::class)
            ->syncFromConsent($client);

        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-'.Str::upper(Str::random(8)),
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $primary = app(NotificationTriggerService::class)->sendBookingConfirmation($appointment);
        $this->assertNotNull($primary);
        $this->assertSame(NotificationChannel::EMAIL, $primary->channel);

        $this->assertTrue(
            NotificationMessage::query()
                ->where('client_id', $client->id)
                ->where('purpose', NotificationPurpose::BOOKING_CONFIRMATION)
                ->where('channel', NotificationChannel::IN_APP)
                ->whereIn('status', [NotificationMessageStatus::SENT, NotificationMessageStatus::QUEUED, NotificationMessageStatus::DELIVERED])
                ->exists()
        );

        $this->assertTrue(
            ClientNotice::query()
                ->where('client_id', $client->id)
                ->where('type', ClientNotice::TYPE_OPERATIONAL_IN_APP)
                ->exists()
        );
    }

    public function test_can_create_in_app_template_via_api(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/templates', [
                'name' => 'Custom in-app',
                'channel' => NotificationChannel::IN_APP,
                'category' => 'booking',
                'subject' => 'Heads up',
                'body_text' => 'See you soon',
            ])
            ->assertCreated()
            ->assertJsonPath('data.channel', NotificationChannel::IN_APP);
    }
}
