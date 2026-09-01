<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Marketing\Services\MarketingEmailLayoutService;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Services\ClientMembershipService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Domains\Memberships\Services\MembershipPlanService;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Models\NotificationMessage;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module2CrmJoinWelcomeAndMemberPortalTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_crm_join_sends_branded_welcome_email_when_email_present(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $ctx['tenant']->update([
            'contact_email' => 'owner.salon@example.test',
            'owner_whatsapp' => '+447700900777',
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Welcome',
                'last_name' => 'Guest',
                'whatsapp_number' => '+447700900501',
                'email' => 'welcome@example.test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.lucky_position', 1)
            ->assertJsonPath('data.lucky_cap', 50)
            ->assertJsonPath('data.total_customer_count', 1)
            ->assertJsonPath('data.lucky_eligible', true);

        $this->assertDatabaseHas('notifications_messages', [
            'purpose' => NotificationPurpose::CRM_JOIN_WELCOME,
            'recipient_address' => 'welcome@example.test',
            'channel' => NotificationChannel::EMAIL,
        ]);

        $this->assertDatabaseHas('notifications_messages', [
            'purpose' => NotificationPurpose::CRM_JOIN_WELCOME,
            'recipient_address' => '+447700900501',
            'channel' => NotificationChannel::WHATSAPP,
        ]);

        $this->assertDatabaseHas('notifications_messages', [
            'purpose' => NotificationPurpose::CRM_JOIN_TENANT_ALERT,
            'recipient_address' => 'owner.salon@example.test',
            'channel' => NotificationChannel::EMAIL,
        ]);

        $this->assertDatabaseHas('notifications_messages', [
            'purpose' => NotificationPurpose::CRM_JOIN_TENANT_ALERT,
            'recipient_address' => '+447700900777',
            'channel' => NotificationChannel::WHATSAPP,
        ]);

        $message = NotificationMessage::query()
            ->where('purpose', NotificationPurpose::CRM_JOIN_WELCOME)
            ->where('channel', NotificationChannel::EMAIL)
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Thank you and Welcome', (string) $message->subject);
        $this->assertStringContainsString('Install App', (string) $message->body_html);
        $this->assertStringContainsString('1 / 50 lucky customer', (string) $message->body_html);
        $this->assertStringContainsString(MarketingEmailLayoutService::POWERED_BY, (string) $message->body_html);
        $this->assertStringContainsString('Anek Latin', (string) $message->body_html);
        $this->assertStringContainsString('/book/'.$ctx['tenant']->slug.'?install=pwa', (string) $message->body_html);
        $this->assertMatchesRegularExpression(
            '#href="https?://[^"]+/book/'.preg_quote($ctx['tenant']->slug, '#').'\?install=pwa"#',
            (string) $message->body_html,
        );
        $this->assertSame(
            rtrim((string) config('app.frontend_url'), '/').'/book/'.$ctx['tenant']->slug.'?install=pwa',
            data_get($message->metadata, 'pwa_url'),
        );

        $tenantAlert = NotificationMessage::query()
            ->where('purpose', NotificationPurpose::CRM_JOIN_TENANT_ALERT)
            ->where('channel', NotificationChannel::EMAIL)
            ->first();
        $this->assertNotNull($tenantAlert);
        $this->assertStringContainsString('Test Salon', (string) $tenantAlert->body_text);
        $this->assertStringContainsString('total customer count is now "1"', (string) $tenantAlert->body_text);
        $this->assertStringContainsString('/login', (string) $tenantAlert->body_text);
    }

    public function test_crm_join_increments_total_count_and_stops_lucky_after_cap(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $tenantId = $ctx['tenant']->id;

        for ($i = 1; $i <= 50; $i++) {
            Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'first_name' => 'Existing',
                'last_name' => (string) $i,
                'email' => "existing{$i}@example.test",
                'phone' => '+4477009'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'membership_joined_at' => now()->subDays(60 - $i),
                'is_active' => true,
            ]);
        }

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Lucky',
                'whatsapp_number' => '+447700900888',
                'email' => 'lucky51@example.test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.lucky_position', 51)
            ->assertJsonPath('data.total_customer_count', 51)
            ->assertJsonPath('data.lucky_eligible', false);

        $welcome = NotificationMessage::query()
            ->where('purpose', NotificationPurpose::CRM_JOIN_WELCOME)
            ->where('channel', NotificationChannel::EMAIL)
            ->where('recipient_address', 'lucky51@example.test')
            ->first();
        $this->assertNotNull($welcome);
        $this->assertStringNotContainsString('lucky customer', (string) $welcome->body_html);
    }

    public function test_crm_join_second_customer_updates_total_count(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $ctx['tenant']->update(['contact_email' => 'owner.salon@example.test']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'First',
                'whatsapp_number' => '+447700900601',
                'email' => 'first@example.test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.total_customer_count', 1);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Second',
                'whatsapp_number' => '+447700900602',
                'email' => 'second@example.test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.lucky_position', 2)
            ->assertJsonPath('data.total_customer_count', 2)
            ->assertJsonPath('data.lucky_eligible', true);

        $tenantAlert = NotificationMessage::query()
            ->where('purpose', NotificationPurpose::CRM_JOIN_TENANT_ALERT)
            ->where('channel', NotificationChannel::EMAIL)
            ->where('recipient_address', 'owner.salon@example.test')
            ->orderByDesc('created_at')
            ->first();
        $this->assertNotNull($tenantAlert);
        $this->assertStringContainsString('total customer count is now "2"', (string) $tenantAlert->body_text);
    }

    public function test_member_portal_login_and_membership_tier_booking_gate(): void
    {
        $ctx = $this->seedTenantContext([
            'crm.view', 'crm.manage', 'memberships.view', 'memberships.manage',
            'booking.view', 'booking.manage',
        ]);
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Member',
            'last_name' => 'User',
            'email' => 'member@example.test',
            'phone' => '+447700900502',
            'is_active' => true,
        ]);

        $plan = app(MembershipPlanService::class)->create([
            'name' => 'Club',
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 5000,
            'is_public' => true,
        ]);
        app(ClientMembershipService::class)->assign([
            'client_id' => $client->id,
            'membership_plan_id' => $plan->id,
            'status' => ClientMembershipStatus::ACTIVE,
        ], $ctx['teamMember']->id);

        app(LoyaltyRedemptionSettingsService::class)->update([
            'is_loyalty_redemption_enabled' => true,
            'points_per_redemption_block' => 100,
            'value_cents_per_block' => 1000,
        ]);

        $unknown = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login/request-otp', [
                'email' => 'missing@example.test',
                'phone' => '+447700900599',
            ]);
        $unknown->assertStatus(422);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'member@example.test', '+44 7700 900502');
        $this->assertNotEmpty($token);

        $this->withHeaders([
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/member/me')
            ->assertOk()
            ->assertJsonPath('data.client.email', 'member@example.test')
            ->assertJsonPath('data.benefits.has_membership', true)
            ->assertJsonPath('data.benefits.loyalty_eligible', true);
    }
}
