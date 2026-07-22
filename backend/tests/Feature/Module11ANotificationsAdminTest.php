<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Notifications\Enums\NotificationCategory;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Enums\NotificationSourceType;
use App\Domains\Notifications\Models\NotificationTemplate;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module11ANotificationsAdminTest extends TestCase
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
            'notifications.reporting.view',
            'crm.view',
            'crm.manage',
            'booking.view',
            'booking.manage',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $attributes
     * @param  array<string, bool>  $consents
     */
    protected function makeClient(array $ctx, array $attributes = [], array $consents = []): Client
    {
        $client = Client::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Client',
            'last_name' => Str::random(6),
            'email' => 'client.'.Str::lower(Str::random(8)).'@example.com',
            'phone' => '+4477'.mt_rand(10000000, 99999999),
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ], $attributes));

        foreach ($consents as $type => $granted) {
            ClientConsentRecord::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'client_id' => $client->id,
                'consent_type' => $type,
                'granted' => $granted,
                'source' => ClientConsentRecord::SOURCE_STAFF_ENTRY,
                'recorded_at' => now(),
            ]);
        }

        return $client;
    }

    public function test_template_crud_and_archive(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/templates', [
                'name' => 'Booking reminder',
                'channel' => NotificationChannel::EMAIL,
                'category' => NotificationCategory::BOOKING,
                'subject' => 'Reminder',
                'body_text' => 'Hi {{client_first_name}}',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Booking reminder')
            ->assertJsonPath('data.slug', 'booking-reminder');

        $id = $created->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_template.created']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/templates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/notifications/templates/{$id}", ['name' => 'Booking reminder v2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Booking reminder v2');
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_template.updated']);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/notifications/templates/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_template.archived']);
    }

    public function test_system_template_cannot_be_modified_or_archived(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $systemTemplate = NotificationTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'System booking reminder',
            'slug' => 'system-booking-reminder',
            'channel' => NotificationChannel::EMAIL,
            'category' => NotificationCategory::BOOKING,
            'body_text' => 'System copy',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/notifications/templates/{$systemTemplate->id}", ['name' => 'Hijacked'])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/notifications/templates/{$systemTemplate->id}/archive")
            ->assertStatus(422);

        $this->assertDatabaseHas('notifications_templates', [
            'id' => $systemTemplate->id,
            'name' => 'System booking reminder',
            'is_active' => true,
        ]);
    }

    public function test_manual_message_accepts_template_reference(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $template = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/templates', [
                'name' => 'Manual base',
                'channel' => NotificationChannel::EMAIL,
                'category' => NotificationCategory::GENERAL,
                'subject' => 'Base subject',
                'body_text' => 'Base body',
            ])->assertCreated();
        $templateId = $template->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'purpose' => NotificationPurpose::MANUAL_CLIENT_MESSAGE,
                'notification_template_id' => $templateId,
                'subject' => 'Base subject',
                'body_text' => 'Base body',
            ])
            ->assertCreated()
            ->assertJsonPath('data.notification_template_id', $templateId)
            ->assertJsonPath('data.status', NotificationMessageStatus::SENT);
    }

    public function test_reporting_failures_returns_structured_rows(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx, ['email' => 'fail-'.Str::random(4).'@example.com']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Will fail',
            ])->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/reporting/failures')
            ->assertOk()
            ->assertJsonPath('data.0.status', NotificationMessageStatus::FAILED)
            ->assertJsonPath('data.0.channel', NotificationChannel::EMAIL)
            ->assertJsonPath('data.0.purpose', NotificationPurpose::MANUAL_CLIENT_MESSAGE)
            ->assertJsonPath('data.0.failure_reason', 'Simulated provider failure.');
    }

    public function test_tenant_isolation_on_preferences(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);
        $otherCtx = $this->seedOtherTenantOwner($ctx);

        // Owner in tenant B cannot read tenant A client's preferences.
        $this->withTenantAuth($otherCtx['token'], $otherCtx['tenant']->slug)
            ->getJson("/api/v1/admin/notifications/preferences/{$client->id}")
            ->assertNotFound();

        // Nor update them.
        $this->withTenantAuth($otherCtx['token'], $otherCtx['tenant']->slug)
            ->putJson("/api/v1/admin/notifications/preferences/{$client->id}", ['allow_email' => false])
            ->assertNotFound();
    }

    public function test_manual_message_create_and_send(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'subject' => 'Hello',
                'body_text' => 'A manual note',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationMessageStatus::SENT)
            ->assertJsonPath('data.source_type', NotificationSourceType::MANUAL)
            ->assertJsonPath('data.purpose', NotificationPurpose::MANUAL_CLIENT_MESSAGE);

        $messageId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.sent']);
        $this->assertDatabaseHas('notifications_message_attempts', [
            'notification_message_id' => $messageId,
            'status' => NotificationMessageStatus::SENT,
        ]);
    }

    public function test_message_listing_detail_and_attempts(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Detail test',
            ])->assertCreated();

        $id = $created->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/messages?status='.NotificationMessageStatus::SENT)
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/notifications/messages/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonCount(1, 'data.attempts');
    }

    public function test_failed_simulated_message_path(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx, ['email' => 'fail-'.Str::random(4).'@example.com']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Should fail',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationMessageStatus::FAILED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.failed']);
    }

    public function test_suppressed_message_path_based_on_preference(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        // Disable booking notifications for this client.
        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/notifications/preferences/{$client->id}", [
                'booking_notifications' => false,
            ])->assertOk();

        app(TenantContext::class)->set($ctx['tenant']);
        $message = app(\App\Domains\Notifications\Services\NotificationMessageService::class)->createSystemMessage([
            'client_id' => $client->id,
            'source_type' => NotificationSourceType::BOOKING,
            'purpose' => NotificationPurpose::BOOKING_REMINDER,
            'channel' => NotificationChannel::EMAIL,
            'body_text' => 'Reminder',
        ]);

        $this->assertSame(NotificationMessageStatus::SUPPRESSED, $message->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.suppressed']);
    }

    public function test_preference_get_and_update(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/notifications/preferences/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.allow_email', true);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/notifications/preferences/{$client->id}", [
                'allow_sms' => false,
                'preferred_channel' => NotificationChannel::EMAIL,
            ])
            ->assertOk()
            ->assertJsonPath('data.allow_sms', false)
            ->assertJsonPath('data.preferred_channel', NotificationChannel::EMAIL);

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_preference.updated']);
    }

    public function test_sync_preferences_from_consent(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx, [], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
            ClientConsentRecord::TYPE_MARKETING_SMS => false,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/notifications/preferences/{$client->id}/sync-from-consent")
            ->assertOk()
            ->assertJsonPath('data.allow_email', true)
            ->assertJsonPath('data.allow_sms', false)
            ->assertJsonPath('data.preferred_channel', NotificationChannel::EMAIL);

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_preference.synced_from_consent']);
    }

    public function test_settings_get_and_update(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/settings')
            ->assertOk()
            ->assertJsonPath('data.booking_reminders_enabled', true)
            ->assertJsonPath('data.default_booking_reminder_hours', 24);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/notifications/settings', [
                'booking_reminders_enabled' => false,
                'sender_name' => 'My Salon',
            ])
            ->assertOk()
            ->assertJsonPath('data.booking_reminders_enabled', false)
            ->assertJsonPath('data.sender_name', 'My Salon');

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_automation_settings.updated']);
    }

    public function test_reporting_summary_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'A message',
            ])->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/reporting/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.successful', 1);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/reporting/by-purpose')
            ->assertOk()
            ->assertJsonPath('data.0.purpose', NotificationPurpose::MANUAL_CLIENT_MESSAGE);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/reporting/failures')
            ->assertOk();
    }

    public function test_client_timeline_endpoint(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'subject' => 'Timeline entry',
                'body_text' => 'Timeline body',
            ])->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/notifications/timeline/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.0.source', 'notification')
            ->assertJsonPath('data.0.purpose', NotificationPurpose::MANUAL_CLIENT_MESSAGE);
    }

    public function test_message_cancel_and_mark_delivered(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        app(TenantContext::class)->set($ctx['tenant']);
        // Create without dispatch so it stays queued and can be cancelled.
        $queued = app(\App\Domains\Notifications\Services\NotificationMessageService::class)->createSystemMessage([
            'client_id' => $client->id,
            'source_type' => NotificationSourceType::MANUAL,
            'purpose' => NotificationPurpose::MANUAL_CLIENT_MESSAGE,
            'channel' => NotificationChannel::EMAIL,
            'body_text' => 'Queued',
        ], dispatch: false);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/notifications/messages/{$queued->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', NotificationMessageStatus::CANCELLED);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.cancelled']);

        // A sent message can be marked delivered.
        $sent = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Sent',
            ])->assertCreated();
        $sentId = $sent->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/notifications/messages/{$sentId}/mark-delivered")
            ->assertOk()
            ->assertJsonPath('data.status', NotificationMessageStatus::DELIVERED);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_message.delivered']);
    }

    public function test_desk_note_creates_internal_feed_message(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/desk', [
                'body_text' => 'Walk-in on chair 2 needs colour check',
            ])
            ->assertCreated()
            ->assertJsonPath('data.channel', NotificationChannel::INTERNAL_NOTE)
            ->assertJsonPath('data.purpose', NotificationPurpose::INTERNAL_NOTE_DELIVERY);

        $this->assertTrue((bool) ($created->json('data.metadata.desk_chat') ?? false));

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/messages?desk_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created->json('data.id'));
    }

    public function test_trigger_service_creates_booking_confirmation(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = $this->makeClient($ctx);
        $appointment = Appointment::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'confirmed',
        ]);

        $message = app(NotificationTriggerService::class)->sendBookingConfirmation($appointment);

        $this->assertNotNull($message);
        $this->assertSame(NotificationPurpose::BOOKING_CONFIRMATION, $message->purpose);
        $this->assertSame(NotificationSourceType::BOOKING, $message->source_type);
        $this->assertDatabaseHas('notifications_messages', [
            'appointment_id' => $appointment->id,
            'purpose' => NotificationPurpose::BOOKING_CONFIRMATION,
        ]);
    }

    public function test_tenant_isolation_on_message_show(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Isolated',
            ])->assertCreated();
        $id = $created->json('data.id');

        // Other tenant owner cannot access it.
        $otherCtx = $this->seedOtherTenantOwner($ctx);

        $this->withTenantAuth($otherCtx['token'], $otherCtx['tenant']->slug)
            ->getJson("/api/v1/admin/notifications/messages/{$id}")
            ->assertNotFound();
    }

    public function test_permission_gate_blocks_manage_without_permission(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        // Viewer role only has identity.view — no notifications permissions.
        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/notifications/templates', [
                'name' => 'Nope',
                'channel' => NotificationChannel::EMAIL,
                'category' => NotificationCategory::BOOKING,
            ])
            ->assertForbidden();
    }

    public function test_reporting_permission_gate(): void
    {
        $ctx = $this->seedTenantContext(['notifications.view', 'crm.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/notifications/reporting/summary')
            ->assertForbidden();
    }

    /**
     * Build an owner in a second tenant for isolation checks.
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function seedOtherTenantOwner(array $ctx): array
    {
        $otherTenant = $ctx['otherTenant'];

        $role = \App\Domains\Identity\Models\Role::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->permissions()->sync($this->modulePermissions());

        $user = \App\Domains\Identity\Models\User::factory()->create([
            'email' => 'other-owner@test.local',
        ]);

        $member = \App\Domains\Identity\Models\TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $user->id,
            'employment_type' => \App\Domains\Identity\Models\TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Other Owner',
            'is_active' => true,
        ]);
        $member->roles()->attach($role->id);

        return [
            'tenant' => $otherTenant,
            'token' => $user->createToken('other-owner')->plainTextToken,
        ];
    }
}
