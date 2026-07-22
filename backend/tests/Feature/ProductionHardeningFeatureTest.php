<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\PlatformInvoice;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ProductionHardeningFeatureTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function makeClient(array $ctx, array $attributes = []): Client
    {
        app(TenantContext::class)->set($ctx['tenant']);

        return Client::query()->create(array_merge([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Pat',
            'last_name' => 'Client',
            'email' => 'pat@example.com',
            'phone' => '+15550009999',
            'is_active' => true,
        ], $attributes));
    }

    public function test_webhook_with_invalid_signature_is_rejected_when_secret_configured(): void
    {
        config(['integrations.webhooks.require_valid_signature' => true]);

        $ctx = $this->seedTenantContext(['integrations.view', 'integrations.manage']);

        $account = ProviderAccount::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Stripe',
            'category' => ProviderCategory::PAYMENT_GATEWAY,
            'driver' => ProviderDriver::STRIPE,
            'is_default' => true,
            'status' => 'active',
            'webhook_secret' => 'whsec_test_secret',
            'credentials_json' => ['secret_key' => 'sk_test'],
        ]);

        $this->postJson('/api/v1/integrations/webhooks/stripe', [
            'tenant_id' => $ctx['tenant']->id,
            'provider_account_id' => $account->id,
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_123',
        ], [
            'Stripe-Signature' => 't=1,v1=deadbeef',
        ])->assertUnauthorized();
    }

    public function test_webhook_with_valid_stripe_signature_is_accepted(): void
    {
        config(['integrations.webhooks.require_valid_signature' => true]);

        $ctx = $this->seedTenantContext(['integrations.view', 'integrations.manage']);
        $secret = 'whsec_test_secret';

        $account = ProviderAccount::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Stripe',
            'category' => ProviderCategory::PAYMENT_GATEWAY,
            'driver' => ProviderDriver::STRIPE,
            'is_default' => true,
            'status' => 'active',
            'webhook_secret' => $secret,
            'credentials_json' => ['secret_key' => 'sk_test'],
        ]);

        $payload = [
            'tenant_id' => $ctx['tenant']->id,
            'provider_account_id' => $account->id,
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_456',
        ];
        $body = json_encode($payload);
        $timestamp = (string) time();
        $sig = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->call(
            'POST',
            '/api/v1/integrations/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$sig,
            ],
            $body,
        )->assertCreated()
            ->assertJsonPath('data.signature_valid', true);
    }

    public function test_platform_suspend_impersonate_and_billing_dunning(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Sanctum::actingAs($admin);

        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage', 'identity.view']);
        $tenant = $ctx['tenant'];

        $plan = $ctx['plan'];
        $plan->display_price_cents = 4900;
        $plan->save();

        $subscription = TenantSubscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);
        $subscription->subscription_plan_id = $plan->id;
        $subscription->status = TenantSubscription::STATUS_ACTIVE;
        $subscription->current_period_start = now()->subMonth();
        $subscription->current_period_end = now()->subDay();
        $subscription->billing_customer_id = null;
        $subscription->save();

        $this->postJson('/api/v1/platform/tenants/'.$tenant->id.'/suspend', [
            'reason' => 'Non-payment',
        ])->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson('/api/v1/platform/tenants/'.$tenant->id.'/unsuspend')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson('/api/v1/platform/tenants/'.$tenant->id.'/impersonate')
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user', 'tenant', 'impersonated_by']]);

        $this->postJson('/api/v1/platform/billing/process', [
            'generate' => true,
            'collect' => true,
        ])->assertOk();

        $invoice = PlatformInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(PlatformInvoice::STATUS_PAST_DUE, $invoice->status);

        $this->postJson('/api/v1/platform/billing/invoices/'.$invoice->id.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('data.status', PlatformInvoice::STATUS_PAID);
    }

    public function test_client_export_and_erase(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $client = $this->makeClient($ctx, [
            'email' => 'export@example.com',
            'phone' => '+15550001111',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/export')
            ->assertOk()
            ->assertJsonPath('data.client.email', 'export@example.com');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/erase', [
                'confirm' => true,
                'reason' => 'GDPR request',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_member_bootstrap_exposes_vapid_public_key(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
        ])->getJson('/api/v1/member/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonStructure(['data' => ['vapid_public_key']]);
    }

    public function test_live_mailgun_http_dispatch_from_notification_path(): void
    {
        Http::fake([
            'api.mailgun.net/*' => Http::response(['id' => '<msg@mg.example.com>', 'message' => 'Queued'], 200),
        ]);

        $ctx = $this->seedTenantContext([
            'notifications.view',
            'notifications.manage',
            'integrations.view',
            'integrations.manage',
            'crm.view',
            'crm.manage',
        ]);
        $client = $this->makeClient($ctx);

        ProviderAccount::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Mailgun',
            'category' => ProviderCategory::EMAIL,
            'driver' => ProviderDriver::MAILGUN,
            'is_default' => true,
            'status' => 'active',
            'from_address' => 'noreply@mg.example.com',
            'credentials_json' => ['api_key' => 'key-test', 'domain' => 'mg.example.com'],
        ]);

        app(TenantContext::class)->set($ctx['tenant']);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/notifications/messages/manual', [
                'client_id' => $client->id,
                'channel' => 'email',
                'body_text' => 'Booking confirmation',
                'subject' => 'Confirmed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.mailgun.net'));
    }
}
