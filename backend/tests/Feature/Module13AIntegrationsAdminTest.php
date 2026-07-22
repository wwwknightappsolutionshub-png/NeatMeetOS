<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Integrations\Enums\ProviderAccountStatus;
use App\Domains\Integrations\Enums\ProviderAttemptStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Enums\ProviderSourceDomain;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Domains\Integrations\Models\ProviderDeliveryAttempt;
use App\Domains\Integrations\Models\ProviderWebhookEvent;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
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

class Module13AIntegrationsAdminTest extends TestCase
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

    public function test_provider_account_crud_set_default_archive_and_test(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Primary Email',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
                'from_address' => 'noreply@demo.local',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category', ProviderCategory::EMAIL)
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_account.created']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/integrations/provider-accounts/'.$created, [
                'name' => 'Primary Email v2',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Primary Email v2');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts/'.$created.'/test')
            ->assertOk()
            ->assertJsonPath('data.last_test_result', 'simulation_ok');

        $this->assertNotNull(
            ProviderAccount::query()->find($created)?->last_tested_at
        );

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/integrations/provider-accounts/'.$created.'/archive')
            ->assertOk();

        $this->assertDatabaseHas('provider_accounts', [
            'id' => $created,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_account.archived']);
    }

    public function test_only_one_default_provider_account_per_category_per_tenant(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $first = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email A',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $second = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email B',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/integrations/provider-accounts/'.$second.'/set-default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('provider_accounts', ['id' => $first, 'is_default' => false]);
        $this->assertDatabaseHas('provider_accounts', ['id' => $second, 'is_default' => true]);
    }

    public function test_archived_or_inactive_account_cannot_be_set_default(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'SMS Provider',
                'category' => ProviderCategory::SMS,
                'driver' => ProviderDriver::SIMULATION,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/integrations/provider-accounts/'.$accountId.'/deactivate')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/integrations/provider-accounts/'.$accountId.'/set-default')
            ->assertStatus(422);
    }

    public function test_notification_manual_send_creates_provider_delivery_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email Sim',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated();

        $message = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'subject' => 'Hello',
                'body_text' => 'Test body',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationMessageStatus::SENT);

        $messageId = $message->json('data.id');

        $this->assertDatabaseHas('notifications_message_attempts', [
            'notification_message_id' => $messageId,
        ]);
        $this->assertDatabaseHas('provider_delivery_attempts', [
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_id' => $messageId,
            'status' => ProviderAttemptStatus::DELIVERED,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_attempt.sent']);
    }

    public function test_marketing_workflow_test_run_creates_provider_delivery_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $template = $this->makeMarketingTemplate($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL);
        $workflow = $this->makeMarketingWorkflow($ctx, MarketingWorkflowTrigger::MANUAL, MarketingChannel::EMAIL, $template->id);
        $client = $this->makeClient($ctx, [], [
            ClientConsentRecord::TYPE_MARKETING_EMAIL => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/workflows/'.$workflow->id.'/run-test', [
                'client_id' => $client->id,
            ])
            ->assertCreated();

        $messageId = MarketingMessage::query()
            ->where('client_id', $client->id)
            ->value('id');

        $this->assertNotNull($messageId);
        $this->assertDatabaseHas('provider_delivery_attempts', [
            'source_domain' => ProviderSourceDomain::MARKETING,
            'source_id' => $messageId,
            'status' => ProviderAttemptStatus::DELIVERED,
        ]);
    }

    public function test_payment_link_creates_provider_delivery_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe Sim',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated();

        $txnId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/payments/payment-link', [
                'transaction_type' => PaymentTransactionType::DEPOSIT,
                'amount_cents' => 2500,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('provider_delivery_attempts', [
            'source_domain' => ProviderSourceDomain::PAYMENTS,
            'related_payment_transaction_id' => $txnId,
            'category' => ProviderCategory::PAYMENT_GATEWAY,
            'status' => ProviderAttemptStatus::DELIVERED,
        ]);
    }

    public function test_webhook_ingest_stores_event_and_admin_can_list_detail(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::STRIPE,
            ])
            ->assertCreated()
            ->json('data.id');

        $response = $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'tenant_id' => $ctx['tenant']->id,
            'provider_account_id' => $accountId,
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_test_123',
            'data' => ['object' => ['id' => 'pi_123']],
        ])->assertCreated();

        $eventId = $response->json('data.id');

        $this->assertDatabaseHas('provider_webhook_events', [
            'id' => $eventId,
            'driver' => ProviderDriver::STRIPE,
            'event_type' => 'payment_intent.succeeded',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_webhook.received']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $eventId);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.event_type', 'payment_intent.succeeded');
    }

    public function test_provider_attempt_filters_and_detail(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Filter test',
            ])
            ->assertCreated();

        $attemptId = ProviderDeliveryAttempt::query()->value('id');
        $this->assertNotNull($attemptId);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-attempts?source_domain=notifications&client_id='.$client->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $attemptId);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-attempts/'.$attemptId)
            ->assertOk()
            ->assertJsonPath('data.source_domain', ProviderSourceDomain::NOTIFICATIONS);
    }

    public function test_simulation_fallback_when_no_active_provider_account(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'No provider configured',
            ])
            ->assertCreated();

        $attempt = ProviderDeliveryAttempt::query()->first();
        $this->assertNotNull($attempt);
        $this->assertNull($attempt->provider_account_id);
        $this->assertTrue($attempt->metadata_json['simulation_fallback'] ?? false);
        $this->assertSame(ProviderAttemptStatus::DELIVERED, $attempt->status);
    }

    public function test_deactivated_default_routes_via_simulation_fallback(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx);

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email to deactivate',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/integrations/provider-accounts/'.$accountId.'/deactivate')
            ->assertOk();

        $this->assertDatabaseHas('provider_accounts', [
            'id' => $accountId,
            'is_default' => false,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'After deactivate',
            ])
            ->assertCreated();

        $attempt = ProviderDeliveryAttempt::query()->latest('created_at')->first();
        $this->assertNull($attempt->provider_account_id);
        $this->assertTrue($attempt->metadata_json['simulation_fallback'] ?? false);
    }

    public function test_failed_attempt_can_be_retried_under_simulation(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $attempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'recipient_address' => 'retry-me@example.com',
            'status' => ProviderAttemptStatus::FAILED,
            'failed_at' => now(),
            'failure_message' => 'Simulated provider failure.',
            'requested_at' => now(),
            'metadata_json' => ['driver' => ProviderDriver::SIMULATION],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-attempts/'.$attempt->id.'/retry')
            ->assertOk()
            ->assertJsonPath('data.result.status', ProviderAttemptStatus::DELIVERED);

        $this->assertDatabaseHas('provider_delivery_attempts', [
            'id' => $attempt->id,
            'status' => ProviderAttemptStatus::DELIVERED,
        ]);
    }

    public function test_tenant_isolation_on_provider_resources(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignAccount = ProviderAccount::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'category' => ProviderCategory::EMAIL,
            'driver' => ProviderDriver::SIMULATION,
            'status' => ProviderAccountStatus::ACTIVE,
        ]);

        $foreignAttempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'status' => ProviderAttemptStatus::DELIVERED,
            'requested_at' => now(),
        ]);

        $foreignEvent = ProviderWebhookEvent::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'driver' => ProviderDriver::STRIPE,
            'event_type' => 'test',
            'received_at' => now(),
            'payload_json' => ['x' => 1],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-accounts/'.$foreignAccount->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-attempts/'.$foreignAttempt->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$foreignEvent->id)
            ->assertNotFound();
    }

    public function test_integrations_permission_gates(): void
    {
        $viewOnly = $this->seedTenantContext(['integrations.view']);

        $this->withTenantAuth($viewOnly['token'])
            ->getJson('/api/v1/admin/integrations/provider-accounts')
            ->assertOk();

        $this->withTenantAuth($viewOnly['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Blocked',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
            ])
            ->assertForbidden();
    }

    public function test_cross_category_defaults_do_not_conflict(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $emailId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email default',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $smsId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'SMS default',
                'category' => ProviderCategory::SMS,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('provider_accounts', ['id' => $emailId, 'is_default' => true]);
        $this->assertDatabaseHas('provider_accounts', ['id' => $smsId, 'is_default' => true]);
    }

    public function test_provider_account_with_credentials_does_not_leak_secrets(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $accountId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Stripe creds',
                'category' => ProviderCategory::PAYMENT_GATEWAY,
                'driver' => ProviderDriver::STRIPE,
                'credentials' => ['secret_key' => 'sk_test_super_secret'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissingPath('data.credentials')
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-accounts/'.$accountId)
            ->assertOk()
            ->assertJsonPath('data.has_credentials', true)
            ->assertJsonMissingPath('data.credentials');
    }

    public function test_notification_fail_recipient_creates_failed_provider_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $client = $this->makeClient($ctx, ['email' => 'fail@example.com']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-accounts', [
                'name' => 'Email Sim',
                'category' => ProviderCategory::EMAIL,
                'driver' => ProviderDriver::SIMULATION,
                'is_default' => true,
            ])
            ->assertCreated();

        $messageId = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => NotificationChannel::EMAIL,
                'body_text' => 'Should fail',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', NotificationMessageStatus::FAILED)
            ->json('data.id');

        $this->assertDatabaseHas('provider_delivery_attempts', [
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_id' => $messageId,
            'status' => ProviderAttemptStatus::FAILED,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'provider_attempt.failed']);
    }

    public function test_webhook_event_detail_returns_payload_and_headers(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $eventId = $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'tenant_id' => $ctx['tenant']->id,
            'type' => 'charge.succeeded',
            'id' => 'evt_detail_1',
            'amount' => 1000,
        ], [
            'X-Test-Header' => 'signed',
        ])->assertCreated()->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/integrations/provider-events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('data.event_type', 'charge.succeeded')
            ->assertJsonPath('data.payload.amount', 1000)
            ->assertJsonPath('data.processing_status', 'received');
    }

    public function test_retry_rejects_non_failed_attempt(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $attempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'status' => ProviderAttemptStatus::DELIVERED,
            'requested_at' => now(),
            'metadata_json' => ['driver' => ProviderDriver::SIMULATION],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-attempts/'.$attempt->id.'/retry')
            ->assertStatus(422);
    }

    public function test_retry_requires_manage_permission(): void
    {
        $viewOnly = $this->seedTenantContext(['integrations.view']);

        $attempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $viewOnly['tenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'status' => ProviderAttemptStatus::FAILED,
            'failed_at' => now(),
            'requested_at' => now(),
            'metadata_json' => ['driver' => ProviderDriver::SIMULATION],
        ]);

        $this->withTenantAuth($viewOnly['token'])
            ->postJson('/api/v1/admin/integrations/provider-attempts/'.$attempt->id.'/retry')
            ->assertForbidden();
    }

    public function test_retry_is_tenant_isolated(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignAttempt = ProviderDeliveryAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'category' => ProviderCategory::EMAIL,
            'source_domain' => ProviderSourceDomain::NOTIFICATIONS,
            'source_type' => 'notification_message',
            'direction' => 'outbound',
            'status' => ProviderAttemptStatus::FAILED,
            'failed_at' => now(),
            'requested_at' => now(),
            'metadata_json' => ['driver' => ProviderDriver::SIMULATION],
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/integrations/provider-attempts/'.$foreignAttempt->id.'/retry')
            ->assertNotFound();
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
