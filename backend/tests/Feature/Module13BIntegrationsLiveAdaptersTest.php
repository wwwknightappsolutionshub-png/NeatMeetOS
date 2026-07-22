<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Enums\ProviderSourceDomain;
use App\Domains\Integrations\Models\ProviderDeliveryAttempt;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationMessageStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module13BIntegrationsLiveAdaptersTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'integrations.view',
            'integrations.manage',
            'integrations.reporting.view',
            'notifications.view',
            'notifications.manage',
            'marketing.view',
            'marketing.manage',
            'marketing.dispatch',
            'payments.view',
            'payments.manage',
            'crm.view',
            'crm.manage',
        ];
    }

    public function test_category_driver_mismatch_is_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Bad combo',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::STRIPE,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['driver']);
    }

    public function test_live_mailgun_account_test_validates_credentials(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $missingId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Mailgun no creds',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::MAILGUN,
                'is_default' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts/'.$missingId.'/test')
            ->assertOk()
            ->assertJsonPath('data.last_test_result', 'credentials_missing');

        $validId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Mailgun ready',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::MAILGUN,
                'credentials' => ['api_key' => 'key-test', 'domain' => 'mg.example.com'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissingPath('data.credentials')
            ->assertJsonPath('data.config_summary.has_api_key', true)
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts/'.$validId.'/test')
            ->assertOk()
            ->assertJsonPath('data.last_test_result', 'credentials_valid_stub');
    }

    public function test_notification_email_via_mailgun_adapter_creates_live_attempt(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.mailgun.net/*' => \Illuminate\Support\Facades\Http::response([
                'id' => '<mg_live_abc123@mg.example.com>',
                'message' => 'Queued. Thank you.',
            ], 200),
        ]);

        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Mailgun Email',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::MAILGUN,
                'is_default' => true,
                'credentials' => ['api_key' => 'key-test', 'domain' => 'mg.example.com'],
            ])
            ->assertCreated();

        $messageId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Live adapter path',
            ])
            ->assertCreated()
            ->json('data.id');

        $attempt = ProviderDeliveryAttempt::query()
            ->where('source_id', $messageId)
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame(ProviderAttemptStatus::DELIVERED, $attempt->status);
        $this->assertStringContainsString('mg_live_abc123', (string) $attempt->provider_reference);
        $this->assertSame(ProviderDriver::MAILGUN, $attempt->metadata_json['driver']);
        $this->assertFalse($attempt->metadata_json['simulated'] ?? true);
        $this->assertSame('http', $attempt->metadata_json['transport']);
    }

    public function test_marketing_sms_via_twilio_adapter_creates_live_attempt(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.twilio.com/*' => \Illuminate\Support\Facades\Http::response([
                'sid' => 'SMlive1234567890',
                'status' => 'queued',
            ], 201),
        ]);

        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Twilio SMS',
                'category' => ProviderCategory::SMS,
                'driver' => ProviderDriver::TWILIO,
                'is_default' => true,
                'phone_number' => '+15551234567',
                'credentials' => [
                    'account_sid' => 'ACtest',
                    'auth_token' => 'token-test',
                    'from_number' => '+15551234567',
                ],
            ])
            ->assertCreated();

        $template = $this->makeMarketingTemplate($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::SMS);
        $workflow = $this->makeMarketingWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::SMS, $template->id);
        $client = $this->makeClient($ctx, [], [
            ClientConsentRecord::TYPE_MARKETING_SMS => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/workflows/'.$workflow->id.'/run-test', [
                'client_id' => $client->id,
            ])
            ->assertCreated();

        $messageId = MarketingMessage::query()->where('client_id', $client->id)->value('id');
        $this->assertNotNull($messageId);

        $attempt = ProviderDeliveryAttempt::query()
            ->where('source_domain', ProviderSourceDomain::MARKETING)
            ->where('source_id', $messageId)
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame(ProviderAttemptStatus::DELIVERED, $attempt->status);
        $this->assertSame('SMlive1234567890', $attempt->provider_reference);
        $this->assertSame(ProviderDriver::TWILIO, $attempt->metadata_json['driver']);
        $this->assertFalse($attempt->metadata_json['simulated'] ?? true);
        $this->assertSame('http', $attempt->metadata_json['transport']);
    }

    public function test_payment_link_via_stripe_adapter_creates_live_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe Gateway',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::STRIPE,
                'is_default' => true,
                'credentials' => ['secret_key' => 'sk_test_123'],
            ])
            ->assertCreated();

        $txnId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/payment-link', [
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'amount_cents' => 5000,
            ])
            ->assertCreated()
            ->json('data.id');

        $attempt = ProviderDeliveryAttempt::query()
            ->where('related_payment_transaction_id', $txnId)
            ->first();

        $this->assertNotNull($attempt);
        $this->assertSame(ProviderAttemptStatus::DELIVERED, $attempt->status);
        $this->assertStringStartsWith('plink_stub_', $attempt->provider_reference);
        $this->assertSame(ProviderDriver::STRIPE, $attempt->metadata_json['driver']);
    }

    public function test_live_account_missing_credentials_falls_back_to_simulation(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Mailgun missing creds',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::MAILGUN,
                'is_default' => true,
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Should fallback',
            ])
            ->assertCreated();

        $attempt = ProviderDeliveryAttempt::query()->latest('created_at')->first();
        $this->assertTrue($attempt->metadata_json['simulation_fallback'] ?? false);
        $this->assertSame('missing_credentials', $attempt->metadata_json['live_fallback_reason']);
        $this->assertTrue($attempt->metadata_json['simulated'] ?? false);
    }

    public function test_live_retry_is_still_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $attempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'status' => ProviderAttemptStatus::FAILED,
            'failed_at' => now(),
            'requested_at' => now(),
            'metadata_json' => ['driver' => ProviderDriver::MAILGUN, 'simulated' => false],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-attempts/'.$attempt->id.'/retry')
            ->assertStatus(422);
    }

    public function test_webhook_ingest_normalizes_stripe_mailgun_and_twilio(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $stripeId = $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'tenant_id' => $ctx['tenant']->id,
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_stripe_1',
            'data' => ['object' => ['id' => 'pi_123', 'object' => 'payment_intent']],
        ])->assertCreated()->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$stripeId)
            ->assertOk()
            ->assertJsonPath('data.event_type', 'payment_intent.succeeded')
            ->assertJsonPath('data.metadata.normalized.object_id', 'pi_123')
            ->assertJsonPath('data.metadata.signature_check', 'skipped');

        $mailgunId = $this->postJson('/api/v1/integrations/webhooks/mailgun', [
            'tenant_id' => $ctx['tenant']->id,
            'event-data' => [
                'event' => 'delivered',
                'id' => 'mg_evt_1',
                'recipient' => 'user@example.com',
                'domain' => 'mg.example.com',
            ],
        ])->assertCreated()->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$mailgunId)
            ->assertOk()
            ->assertJsonPath('data.event_type', 'delivered')
            ->assertJsonPath('data.metadata.normalized.recipient', 'user@example.com');

        $twilioId = $this->postJson('/api/v1/integrations/webhooks/twilio', [
            'tenant_id' => $ctx['tenant']->id,
            'MessageSid' => 'SMtwilio1',
            'MessageStatus' => 'delivered',
            'From' => '+15550001111',
            'To' => '+15550002222',
        ])->assertCreated()->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$twilioId)
            ->assertOk()
            ->assertJsonPath('data.event_type', 'delivered')
            ->assertJsonPath('data.external_event_id', 'SMtwilio1');
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

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeMarketingTemplate(array $ctx, string $category, string $channel): MarketingTemplate
    {
        return MarketingTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title($category).' '.Str::upper($channel),
            'category' => $category,
            'channel' => $channel,
            'subject' => $channel === MarketingChannel::EMAIL ? 'Hello' : null,
            'body_text' => 'Hi there',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function makeMarketingWorkflow(array $ctx, string $trigger, string $channel, ?string $templateId = null): MarketingAutomationWorkflow
    {
        return MarketingAutomationWorkflow::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => Str::title(str_replace('_', ' ', $trigger)).' Workflow',
            'slug' => Str::slug($trigger.'-'.Str::random(4)),
            'trigger_type' => $trigger,
            'channel' => $channel,
            'status' => 'active',
            'template_id' => $templateId,
            'delay_minutes' => 0,
            'allow_repeat' => false,
        ]);
    }
}
