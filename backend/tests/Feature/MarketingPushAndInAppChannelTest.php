<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Services\MarketingDispatchSimulationService;
use App\Domains\Marketing\Services\MarketingEligibilityService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MarketingPushAndInAppChannelTest extends TestCase
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
            'crm.view',
            'crm.manage',
        ];
    }

    public function test_push_eligibility_requires_subscription_and_email_consent(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        $eligibility = app(MarketingEligibilityService::class);

        $missingPush = $eligibility->evaluate($client, MarketingChannel::PUSH);
        $this->assertFalse($missingPush['eligible']);
        $this->assertSame(MarketingEligibilityService::REASON_MISSING_PUSH_SUBSCRIPTION, $missingPush['skipped_reason']);

        MemberPushSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'endpoint' => 'https://push.example/'.$client->id,
            'endpoint_hash' => hash('sha256', 'https://push.example/'.$client->id),
            'p256dh' => 'p256dh',
            'auth' => 'auth',
            'last_seen_at' => now(),
        ]);

        $ok = $eligibility->evaluate($client, MarketingChannel::PUSH);
        $this->assertTrue($ok['eligible']);
        $this->assertSame('push:'.$client->id, $ok['recipient_address']);
    }

    public function test_in_app_dispatch_creates_client_notice_and_member_can_read(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);

        $client = $this->makeClient($ctx);

        $message = MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => MarketingChannel::IN_APP,
            'purpose' => 'broadcast',
            'status' => MarketingMessageStatus::PENDING,
            'recipient_address' => 'client:'.$client->id,
            'subject' => 'Salon offer',
            'rendered_body_text' => 'Hi there — book soon.',
        ]);

        $result = app(MarketingDispatchSimulationService::class)->dispatch($message);

        $this->assertSame(MarketingMessageStatus::SENT, $result['message']->status);
        $this->assertNotNull($result['message']->delivered_at);
        $this->assertDatabaseHas('client_notices', [
            'client_id' => $client->id,
            'marketing_message_id' => $message->id,
            'title' => 'Salon offer',
        ]);

        $token = $this->memberToken($ctx, $client);

        $list = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $ctx['tenant']->slug,
        ])->getJson('/api/v1/member/notices')
            ->assertOk();

        $items = collect($list->json('data.items') ?? []);
        $offer = $items->firstWhere('title', 'Salon offer');
        $this->assertNotNull($offer);
        $this->assertGreaterThanOrEqual(1, (int) $list->json('data.unread_count'));

        $noticeId = $offer['id'];
        $this->assertNotEmpty($noticeId);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Slug' => $ctx['tenant']->slug,
        ])->postJson("/api/v1/member/notices/{$noticeId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $noticeId);

        $this->assertNotNull(ClientNotice::withoutGlobalScopes()->find($noticeId)?->read_at);
    }

    public function test_push_dispatch_simulates_when_vapid_missing_but_subscription_exists(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        app(TenantContext::class)->set($ctx['tenant']);
        config([
            'webpush.vapid.public_key' => null,
            'webpush.vapid.private_key' => null,
        ]);

        $client = $this->makeClient($ctx, [], [ClientConsentRecord::TYPE_MARKETING_EMAIL => true]);
        MemberPushSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'endpoint' => 'https://push.example/sim-'.$client->id,
            'endpoint_hash' => hash('sha256', 'https://push.example/sim-'.$client->id),
            'p256dh' => 'p256dh',
            'auth' => 'auth',
            'last_seen_at' => now(),
        ]);

        $message = MarketingMessage::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $client->id,
            'channel' => MarketingChannel::PUSH,
            'purpose' => 'booking_reminder',
            'status' => MarketingMessageStatus::PENDING,
            'recipient_address' => 'push:'.$client->id,
            'subject' => 'Reminder',
            'rendered_body_text' => 'See you soon',
        ]);

        $result = app(MarketingDispatchSimulationService::class)->dispatch($message);

        $this->assertSame(MarketingMessageStatus::SENT, $result['message']->status);
        $this->assertTrue($result['simulated']);
        $this->assertSame('webpush_simulation', $result['attempt']->provider);
    }

    public function test_template_accepts_push_and_in_app_channels(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates', [
                'name' => 'Push Reminder',
                'category' => 'booking_reminder',
                'channel' => MarketingChannel::PUSH,
                'subject' => 'Heads up',
                'body_text' => 'Hi {{client.first_name}}',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.channel', MarketingChannel::PUSH);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/marketing/templates', [
                'name' => 'In-app Offer',
                'category' => 'broadcast',
                'channel' => MarketingChannel::IN_APP,
                'subject' => 'Offer',
                'body_text' => 'Special for you',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.channel', MarketingChannel::IN_APP);
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
    protected function memberToken(array $ctx, Client $client): string
    {
        app(TenantContext::class)->set($ctx['tenant']);

        $login = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login', [
                'email' => $client->email,
                'phone' => $client->phone,
            ])
            ->assertOk();

        return (string) $login->json('data.token');
    }
}
