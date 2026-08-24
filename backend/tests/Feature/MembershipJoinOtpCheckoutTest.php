<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientPortalToken;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Crm\Services\MemberPortalAuthService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MembershipJoinOtpCheckoutTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_join_requires_terms_and_stores_interested_next_visit(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'preferred_name' => 'Sam',
                'whatsapp_number' => '+447700900801',
                'email' => 'sam@example.test',
                'next_visit_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Sam',
                'whatsapp_number' => '+447700900801',
                'email' => 'sam@example.test',
                'next_visit_date' => now()->addDays(3)->toDateString(),
                'special_date' => '2000-07-20',
                'special_event_label' => 'Birthday',
                'location_id' => $ctx['location']->id,
            ]));

        $create->assertCreated()
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'display_name' => 'Sam',
            'email' => 'sam@example.test',
            'phone' => '+447700900801',
            'special_event_month' => 7,
            'special_event_day' => 20,
        ]);

        $joined = Client::withoutGlobalScopes()->where('email', 'sam@example.test')->first();
        $this->assertNotNull($joined);
        $this->assertSame(
            now()->addDays(3)->toDateString(),
            $joined->interested_next_visit_date?->toDateString(),
        );
        $this->assertNotNull($joined->membership_joined_at);

        $this->assertDatabaseHas('client_consent_records', [
            'client_id' => $create->json('data.client_id'),
            'consent_type' => ClientConsentRecord::TYPE_TERMS_OF_SERVICE,
            'granted' => true,
        ]);

        $this->assertEquals(0, \App\Domains\Booking\Models\Appointment::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->count());
    }

    public function test_member_otp_login_issues_sixty_day_token(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Member',
            'display_name' => 'Member',
            'email' => 'otp@example.test',
            'phone' => '+447700900802',
            'is_active' => true,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'otp@example.test', '+447700900802');
        $this->assertNotEmpty($token);

        $row = ClientPortalToken::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $token))
            ->first();
        $this->assertNotNull($row);
        $this->assertTrue(
            $row->expires_at->greaterThan(now()->addDays(MemberPortalAuthService::TOKEN_TTL_DAYS - 1))
        );
    }

    public function test_check_out_and_admin_whos_in(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Visit',
            'email' => 'visit-out@example.test',
            'phone' => '+447700900803',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'visit-out@example.test', '+447700900803');
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-in', ['location_id' => $ctx['location']->id])
            ->assertOk()
            ->assertJsonPath('data.open_visit.checked_out_at', null);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/visits/open')
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-out')
            ->assertOk()
            ->assertJsonPath('data.open_visit', null);

        $this->assertNotNull(ClientVisit::withoutGlobalScopes()->where('tenant_id', $ctx['tenant']->id)->value('checked_out_at'));
        $this->assertDatabaseHas('client_timeline_events', [
            'event_type' => \App\Domains\Crm\Models\ClientTimelineEvent::EVENT_VISIT_CHECKOUT,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/visits/open')
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        // Same-day re-check-in after clock-out is blocked (one presence visit per day).
        $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-in', ['location_id' => $ctx['location']->id])
            ->assertOk()
            ->assertJsonPath('data.already_checked_in_today', true)
            ->assertJsonPath('data.points', 0);

        $this->assertEquals(1, ClientVisit::withoutGlobalScopes()->where('tenant_id', $ctx['tenant']->id)->count());
    }

    public function test_legacy_client_without_email_can_otp_with_uk_national_phone(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Legacy',
            'phone' => '+447700900804',
            'email' => null,
            'is_active' => true,
        ]);

        $token = $this->memberLoginViaOtp($ctx['tenant']->slug, 'legacy@example.test', '07700900804');
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'phone' => '+447700900804',
            'email' => 'legacy@example.test',
        ]);
    }

    public function test_join_bootstrap_includes_terms_url(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/join/bootstrap')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['tenant', 'locations', 'terms_url', 'offers'],
            ]);
    }
}
