<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Services\ClientMembershipService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Domains\Memberships\Services\MembershipPlanService;
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

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Welcome',
                'last_name' => 'Guest',
                'whatsapp_number' => '+447700900501',
                'email' => 'welcome@example.test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('notifications_messages', [
            'purpose' => NotificationPurpose::CRM_JOIN_WELCOME,
            'recipient_address' => 'welcome@example.test',
        ]);

        $message = NotificationMessage::query()
            ->where('purpose', NotificationPurpose::CRM_JOIN_WELCOME)
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Welcome', (string) $message->subject);
        $this->assertStringContainsString('/member/'.$ctx['tenant']->slug, (string) $message->body_html);
        $this->assertMatchesRegularExpression(
            '#href="https?://[^"]+/member/'.preg_quote($ctx['tenant']->slug, '#').'"#',
            (string) $message->body_html,
        );
        $this->assertSame(
            rtrim((string) config('app.frontend_url'), '/').'/member/'.$ctx['tenant']->slug,
            data_get($message->metadata, 'pwa_url'),
        );
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
