<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientTag;
use App\Domains\Identity\Models\Location;
use App\Domains\Marketing\Enums\MarketingCampaignStatus;
use App\Domains\Marketing\Enums\MarketingCampaignType;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunStatus;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Marketing\Services\MarketingAudienceService;
use App\Domains\Marketing\Services\MarketingAutomationSettingService;
use App\Domains\Marketing\Services\MarketingDispatchSimulationService;
use App\Domains\Marketing\Services\MarketingEligibilityService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module10AMarketingAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'marketing.reporting.view',
            'crm.view',
            'crm.manage',
            'booking.view',
            'booking.manage',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $attributes
     * @param  array<string, bool>  $consents  keyed by consent type
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

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAppointment(array $ctx, Client $client, array $attributes = []): Appointment
    {
        return Appointment::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'location_id' => $ctx['location']->id,
            'client_id' => $client->id,
            'team_member_id' => $ctx['teamMember']->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'booking_reference' => 'NM-'.Str::upper(Str::random(8)),
            'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeTemplate(array $ctx, string $category, string $channel): MarketingTemplate
    {
        return MarketingTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title($category).' '.Str::upper($channel),
            'category' => $category,
            'channel' => $channel,
            'subject' => $channel === MarketingChannel::EMAIL ? 'Hello {{client.first_name}}' : null,
            'body_text' => 'Hi {{client.first_name}}, from {{business.name}}. {{booking.link}} {{review.link}}',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeCampaign(
        array $ctx,
        string $triggerType,
        string $channel,
        ?string $templateId = null,
        string $status = MarketingCampaignStatus::ACTIVE,
    ): MarketingCampaign {
        return MarketingCampaign::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title(str_replace('_', ' ', $triggerType)).' Campaign',
            'campaign_type' => MarketingCampaignType::AUTOMATION,
            'trigger_type' => $triggerType,
            'channel' => $channel,
            'status' => $status,
            'template_id' => $templateId,
            'created_by_team_member_id' => $ctx['teamMember']->id,
        ]);
    }

    public function test_template_crud(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates', [
                'name' => 'Booking Reminder',
                'category' => MarketingTriggerType::BOOKING_REMINDER,
                'channel' => MarketingChannel::EMAIL,
                'subject' => 'Reminder',
                'body_text' => 'Hi {{client.first_name}}',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Booking Reminder')
            ->assertJsonPath('data.channel', MarketingChannel::EMAIL);

        $id = $created->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/templates/{$id}", [
                'name' => 'Booking Reminder v2',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Booking Reminder v2');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/templates/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_template.created']);
        $this->assertDatabaseHas('marketing_templates', ['id' => $id, 'is_active' => false]);
    }

    public function test_install_sample_templates_is_idempotent_and_editable(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $first = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates/install-samples')
            ->assertOk()
            ->assertJsonPath('data.created', 19)
            ->assertJsonPath('data.skipped', 0);

        $this->assertGreaterThan(0, $first->json('data.created'));

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates/install-samples')
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 19);

        $list = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/templates')
            ->assertOk()
            ->json('data');

        $this->assertCount(19, $list);

        $booking = collect($list)->firstWhere('name', 'Sample — Booking reminder');
        $this->assertNotNull($booking);
        $this->assertNotEmpty($booking['body_html']);
        $this->assertFalse($booking['is_system']);

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/templates/{$booking['id']}", [
                'subject' => 'Edited reminder for {{business.name}}',
                'body_html' => '<p>Hi {{client.first_name}}, custom copy.</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Edited reminder for {{business.name}}');
    }

    public function test_audience_preview_by_tag_and_location(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $tag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'VIP',
            'slug' => 'vip',
        ]);

        $vip = $this->makeClient($ctx, ['first_name' => 'Vera'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);
        $vip->tags()->attach($tag->id);

        // Untagged client on the primary location.
        $this->makeClient($ctx, ['first_name' => 'Nan'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);

        // Client on a different location.
        $branch = Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Branch',
            'slug' => 'branch',
            'timezone' => 'Europe/London',
            'is_active' => true,
        ]);
        $this->makeClient($ctx, ['first_name' => 'Otto', 'primary_location_id' => $branch->id], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);

        $audienceService = app(MarketingAudienceService::class);

        $byTag = $audienceService->previewRules(['client_tag_ids' => [$tag->id]], MarketingChannel::EMAIL);
        $this->assertSame(1, $byTag['counts']['matched']);
        $this->assertSame(1, $byTag['counts']['eligible']);
        $this->assertSame($vip->id, $byTag['eligible_sample'][0]['client_id']);

        $byLocation = $audienceService->previewRules(['location_ids' => [$ctx['location']->id]], MarketingChannel::EMAIL);
        $this->assertSame(2, $byLocation['counts']['matched']);
        $this->assertSame(2, $byLocation['counts']['eligible']);
    }

    public function test_consent_suppression_for_sms_and_email(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        // Email consent granted, SMS consent explicitly withdrawn.
        $this->makeClient($ctx, [], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
            ClientConsentRecord::TYPE_MARKETING_SMS => false,
        ]);

        $audienceService = app(MarketingAudienceService::class);

        $email = $audienceService->previewRules([], MarketingChannel::EMAIL);
        $this->assertSame(1, $email['counts']['eligible']);
        $this->assertSame(0, $email['counts']['skipped']);

        $sms = $audienceService->previewRules([], MarketingChannel::SMS);
        $this->assertSame(0, $sms['counts']['eligible']);
        $this->assertSame(1, $sms['counts']['skipped']);
        $this->assertArrayHasKey(
            MarketingEligibilityService::REASON_NO_CHANNEL_CONSENT,
            $sms['counts']['by_reason'],
        );
    }

    public function test_campaign_crud_and_status_change(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL);

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/campaigns', [
                'name' => '24h Reminder',
                'campaign_type' => MarketingCampaignType::AUTOMATION,
                'trigger_type' => MarketingTriggerType::BOOKING_REMINDER,
                'channel' => MarketingChannel::EMAIL,
                'status' => MarketingCampaignStatus::DRAFT,
                'template_id' => $template->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MarketingCampaignStatus::DRAFT);

        $id = $created->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/campaigns/{$id}", [
                'name' => '24h Reminder Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '24h Reminder Updated');

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/campaigns/{$id}/status", [
                'status' => MarketingCampaignStatus::ACTIVE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MarketingCampaignStatus::ACTIVE);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_campaign.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_campaign.status_updated']);
    }

    public function test_booking_reminder_generation_targets_only_eligible_appointments(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL);
        $this->makeCampaign($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL, $template->id);

        $eligible = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $noConsent = $this->makeClient($ctx, [], []);

        $eligibleAppointment = $this->makeAppointment($ctx, $eligible, [
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->makeAppointment($ctx, $noConsent, [
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(6),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        // Outside the 24h reminder window — must not produce a message.
        $farAppointment = $this->makeAppointment($ctx, $eligible, [
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $runId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/booking-reminders/generate')
            ->assertCreated()
            ->assertJsonPath('data.status', MarketingRunStatus::COMPLETED)
            ->json('data.id');

        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $eligible->id,
            'appointment_id' => $eligibleAppointment->id,
            'status' => MarketingMessageStatus::PENDING,
        ]);

        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $noConsent->id,
            'status' => MarketingMessageStatus::SKIPPED,
            'skipped_reason' => MarketingEligibilityService::REASON_NO_CHANNEL_CONSENT,
        ]);

        $this->assertDatabaseMissing('marketing_messages', [
            'appointment_id' => $farAppointment->id,
        ]);
    }

    public function test_review_request_generation_excludes_non_completed_appointments(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::REVIEW_REQUEST, MarketingChannel::SMS);
        $this->makeCampaign($ctx, MarketingTriggerType::REVIEW_REQUEST, MarketingChannel::SMS, $template->id);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_SMS => true]);

        // Default review delay is 24h -> match window is [now-25h, now-24h].
        $windowEnd = now()->subHours(24)->subMinutes(30);

        $completed = $this->makeAppointment($ctx, $client, [
            'starts_at' => $windowEnd->copy()->subHour(),
            'ends_at' => $windowEnd->copy(),
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        // Non-completed appointment in the same window -> excluded.
        $confirmed = $this->makeAppointment($ctx, $client, [
            'starts_at' => $windowEnd->copy()->subHour(),
            'ends_at' => $windowEnd->copy(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $runId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/review-requests/generate')
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'appointment_id' => $completed->id,
            'status' => MarketingMessageStatus::PENDING,
        ]);

        $this->assertDatabaseMissing('marketing_messages', [
            'appointment_id' => $confirmed->id,
        ]);

        $this->assertSame(
            1,
            MarketingMessage::withoutGlobalScopes()->where('marketing_run_id', $runId)->count(),
        );
    }

    public function test_win_back_generation_excludes_clients_with_future_bookings(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        app(MarketingAutomationSettingService::class)->update(['win_back_inactivity_days' => 30]);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::WIN_BACK, MarketingChannel::EMAIL);
        $this->makeCampaign($ctx, MarketingTriggerType::WIN_BACK, MarketingChannel::EMAIL, $template->id);

        $lapsed = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $returning = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);

        // Both last visited 60 days ago (beyond the 30 day inactivity threshold).
        $this->makeAppointment($ctx, $lapsed, [
            'starts_at' => now()->subDays(60)->subHour(),
            'ends_at' => now()->subDays(60),
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        $this->makeAppointment($ctx, $returning, [
            'starts_at' => now()->subDays(60)->subHour(),
            'ends_at' => now()->subDays(60),
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        // Returning client also has an upcoming appointment -> excluded from win-back.
        $this->makeAppointment($ctx, $returning, [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $runId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/win-back/generate')
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $lapsed->id,
            'status' => MarketingMessageStatus::PENDING,
        ]);

        $this->assertDatabaseMissing('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $returning->id,
        ]);
    }

    public function test_dispatch_simulation_creates_attempts_and_statuses(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL);
        $this->makeCampaign($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL, $template->id);

        $willSend = $this->makeClient($ctx, ['email' => 'reachable@example.com'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);
        // Simulation fails when the recipient address contains "fail".
        $willFail = $this->makeClient($ctx, ['email' => 'bounce-fail@example.com'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);

        $this->makeAppointment($ctx, $willSend, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $this->makeAppointment($ctx, $willFail, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $runId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/booking-reminders/generate')
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(2, MarketingMessage::withoutGlobalScopes()
            ->where('marketing_run_id', $runId)
            ->where('status', MarketingMessageStatus::PENDING)
            ->count());

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/runs/{$runId}/dispatch")
            ->assertOk();

        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $willSend->id,
            'status' => MarketingMessageStatus::SENT,
        ]);
        $this->assertDatabaseHas('marketing_messages', [
            'marketing_run_id' => $runId,
            'client_id' => $willFail->id,
            'status' => MarketingMessageStatus::FAILED,
        ]);

        $sentMessageId = MarketingMessage::withoutGlobalScopes()
            ->where('marketing_run_id', $runId)
            ->where('client_id', $willSend->id)
            ->value('id');

        $this->assertDatabaseHas('marketing_message_attempts', [
            'marketing_message_id' => $sentMessageId,
            'status' => MarketingMessageStatus::SENT,
            'provider' => MarketingDispatchSimulationService::PROVIDER,
        ]);

        $failedMessageId = MarketingMessage::withoutGlobalScopes()
            ->where('marketing_run_id', $runId)
            ->where('client_id', $willFail->id)
            ->value('id');

        $this->assertDatabaseHas('marketing_message_attempts', [
            'marketing_message_id' => $failedMessageId,
            'status' => MarketingMessageStatus::FAILED,
        ]);
    }

    public function test_tenant_isolation_on_marketing_resources(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignTemplate = MarketingTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign Template',
            'category' => MarketingTriggerType::BOOKING_REMINDER,
            'channel' => MarketingChannel::EMAIL,
            'body_text' => 'Hi {{client.first_name}}',
            'is_active' => true,
        ]);

        $foreignCampaign = MarketingCampaign::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign Campaign',
            'campaign_type' => MarketingCampaignType::AUTOMATION,
            'trigger_type' => MarketingTriggerType::BOOKING_REMINDER,
            'channel' => MarketingChannel::EMAIL,
            'status' => MarketingCampaignStatus::ACTIVE,
        ]);

        $foreignRun = MarketingRun::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'marketing_campaign_id' => $foreignCampaign->id,
            'trigger_type' => MarketingTriggerType::BOOKING_REMINDER,
            'run_source' => 'manual',
            'status' => MarketingRunStatus::COMPLETED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/templates/{$foreignTemplate->id}")
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/campaigns/{$foreignCampaign->id}")
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson("/api/v1/admin/marketing/runs/{$foreignRun->id}")
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/campaigns')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manage_permission_gate(): void
    {
        $ctx = $this->seedTenantContext(['marketing.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/templates')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates', [
                'name' => 'Blocked',
                'category' => MarketingTriggerType::BOOKING_REMINDER,
                'channel' => MarketingChannel::EMAIL,
                'body_text' => 'Hi {{client.first_name}}',
            ])
            ->assertForbidden();
    }

    public function test_dispatch_permission_gate(): void
    {
        $ctx = $this->seedTenantContext(['marketing.view', 'marketing.manage']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/booking-reminders/generate')
            ->assertForbidden();
    }

    public function test_reporting_permission_gate(): void
    {
        $ctx = $this->seedTenantContext(['marketing.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/summary')
            ->assertForbidden();
    }

    public function test_audience_crud_and_preview_over_http(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $tag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'VIP',
            'slug' => 'vip',
        ]);

        $vip = $this->makeClient($ctx, ['first_name' => 'Vera'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);
        $vip->tags()->attach($tag->id);

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/audiences', [
                'name' => 'VIP Email List',
                'description' => 'High value clients',
                'rules' => ['client_tag_ids' => [$tag->id], 'requires_email_consent' => true],
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'VIP Email List')
            // Proves rules are persisted (regression: previously dropped).
            ->assertJsonPath('data.rules.client_tag_ids.0', $tag->id);

        $id = $created->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_audience.created']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/audiences')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->putJson("/api/v1/admin/marketing/audiences/{$id}", [
                'name' => 'VIP Email List v2',
                'rules' => ['client_tag_ids' => [$tag->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'VIP Email List v2');

        // Preview over HTTP (regression: previously TypeError 500).
        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/audiences/preview', [
                'rules' => ['client_tag_ids' => [$tag->id]],
                'channel' => MarketingChannel::EMAIL,
            ])
            ->assertOk()
            ->assertJsonPath('data.counts.matched', 1)
            ->assertJsonPath('data.counts.eligible', 1);

        $this->withTenantAuth($ctx['token'])
            ->patchJson("/api/v1/admin/marketing/audiences/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_settings_get_and_update_over_http(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        // GET settings (regression: showSettings previously called a missing method).
        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/settings')
            ->assertOk()
            ->assertJsonPath('data.booking_reminder_hours_before', 24);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/marketing/settings', [
                'booking_reminder_hours_before' => 48,
                'win_back_inactivity_days' => 75,
                'review_request_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.booking_reminder_hours_before', 48)
            ->assertJsonPath('data.review_request_enabled', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_automation_settings.updated']);
    }

    public function test_reporting_endpoints_return_summaries(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL);
        $this->makeCampaign($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL, $template->id);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $this->makeAppointment($ctx, $client, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/booking-reminders/generate')
            ->assertCreated();

        // Regression: reporting endpoints previously 500'd (array passed where ?Carbon expected).
        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/summary')
            ->assertOk()
            ->assertJsonPath('data.campaigns.active', 1)
            ->assertJsonStructure(['data' => ['messages', 'runs', 'channels']]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/marketing/reporting/runs')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_run_and_message_audit_events_are_written(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeTemplate($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL);
        $this->makeCampaign($ctx, MarketingTriggerType::BOOKING_REMINDER, MarketingChannel::EMAIL, $template->id);

        $client = $this->makeClient($ctx, ['email' => 'reachable@example.com'], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);
        $this->makeAppointment($ctx, $client, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $runId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/runs/booking-reminders/generate')
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_run.created']);

        $this->withTenantAuth($ctx['token'])
            ->postJson("/api/v1/admin/marketing/runs/{$runId}/dispatch")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_run.dispatched']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketing_message.sent']);
    }
}
