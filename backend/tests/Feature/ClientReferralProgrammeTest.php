<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientReferralConversion;
use App\Domains\Crm\Models\ClientReferralEmailSend;
use App\Domains\Crm\Models\ClientReferralInvite;
use App\Domains\Crm\Services\ClientReferralService;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ClientReferralProgrammeTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_new_join_with_ref_attributes_and_awards_referrer_100(): void
    {
        $ctx = $this->seedTenantContext([
            'crm.view',
            'crm.manage',
            'memberships.view',
            'memberships.manage',
        ]);
        app(TenantContext::class)->set($ctx['tenant']);

        $referrer = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Referrer',
            'email' => 'referrer@example.test',
            'phone' => '+447700900901',
            'is_active' => true,
        ]);

        $invite = app(ClientReferralService::class)->ensureInviteForClient($referrer);

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Friend',
                'whatsapp_number' => '+447700900902',
                'email' => 'friend@example.test',
                'referral_code' => $invite->code,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.created', true);

        $friendId = $create->json('data.client_id');

        $this->assertDatabaseHas('clients', [
            'id' => $friendId,
            'referred_by_client_id' => $referrer->id,
            'referral_invite_id' => $invite->id,
        ]);

        $this->assertDatabaseHas('client_referral_conversions', [
            'referrer_client_id' => $referrer->id,
            'referred_client_id' => $friendId,
            'referrer_points_awarded' => 100,
            'referred_bonus_pending' => true,
        ]);

        $this->assertDatabaseHas('client_loyalty_entries', [
            'client_id' => $referrer->id,
            'entry_type' => LoyaltyEntryType::REFERRAL_REFERRER,
            'points' => 100,
        ]);
    }

    public function test_self_referral_ignored(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $referrer = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Self',
            'email' => 'self@example.test',
            'phone' => '+447700900910',
            'is_active' => true,
        ]);

        $invite = app(ClientReferralService::class)->ensureInviteForClient($referrer);

        // Re-joining same WhatsApp updates existing client (created=false) — no attribution.
        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Self',
                'whatsapp_number' => '+447700900910',
                'email' => 'self@example.test',
                'referral_code' => $invite->code,
            ]))
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $referrer->refresh();
        $this->assertNull($referrer->referred_by_client_id);
        $this->assertSame(0, ClientReferralConversion::query()->count());
        $this->assertSame(0, ClientLoyaltyEntry::query()
            ->where('entry_type', LoyaltyEntryType::REFERRAL_REFERRER)
            ->count());
    }

    public function test_invalid_code_ignored(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'NoCode',
                'whatsapp_number' => '+447700900920',
                'email' => 'nocode@example.test',
                'referral_code' => 'NOTEXIST',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('clients', [
            'phone' => '+447700900920',
            'referred_by_client_id' => null,
        ]);
        $this->assertSame(0, ClientReferralConversion::query()->count());
    }

    public function test_first_purchase_awards_referred_300_once(): void
    {
        $ctx = $this->seedTenantContext([
            'crm.view',
            'crm.manage',
            'memberships.view',
            'memberships.manage',
            'payments.view',
            'payments.manage',
        ]);
        app(TenantContext::class)->set($ctx['tenant']);

        $referrer = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Host',
            'email' => 'host@example.test',
            'phone' => '+447700900930',
            'is_active' => true,
        ]);
        $invite = app(ClientReferralService::class)->ensureInviteForClient($referrer);

        $join = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Guest',
                'whatsapp_number' => '+447700900931',
                'email' => 'guest@example.test',
                'referral_code' => $invite->code,
            ]))
            ->assertCreated();

        $guestId = $join->json('data.client_id');

        $plan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Club',
            'status' => MembershipPlanStatus::ACTIVE,
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 5000,
            'joining_fee_cents' => 0,
            'is_public' => true,
        ]);

        $secondPlan = MembershipPlan::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Club Plus',
            'status' => MembershipPlanStatus::ACTIVE,
            'billing_frequency' => MembershipBillingFrequency::MONTHLY,
            'price_cents' => 7000,
            'joining_fee_cents' => 0,
            'is_public' => true,
        ]);

        $token = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'guest@example.test',
            '+447700900931',
        );
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/purchases', [
                'offer_type' => 'plan',
                'offer_id' => $plan->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('client_loyalty_entries', [
            'client_id' => $guestId,
            'entry_type' => LoyaltyEntryType::REFERRAL_REFERRED,
            'points' => 300,
        ]);
        $this->assertNotNull(Client::withoutGlobalScopes()->find($guestId)?->referral_referred_bonus_awarded_at);
        $this->assertDatabaseHas('client_referral_conversions', [
            'referred_client_id' => $guestId,
            'referred_bonus_pending' => false,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/purchases', [
                'offer_type' => 'plan',
                'offer_id' => $secondPlan->id,
            ])
            ->assertCreated();

        $this->assertSame(1, ClientLoyaltyEntry::query()
            ->where('client_id', $guestId)
            ->where('entry_type', LoyaltyEntryType::REFERRAL_REFERRED)
            ->count());
    }

    public function test_email_invites_accepts_up_to_20_rejects_over(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $referrer = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Sender',
            'email' => 'sender@example.test',
            'phone' => '+447700900940',
            'is_active' => true,
        ]);

        $token = $this->memberLoginViaOtp(
            $ctx['tenant']->slug,
            'sender@example.test',
            '+447700900940',
        );
        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $emails = [];
        for ($i = 1; $i <= 20; $i++) {
            $emails[] = "friend{$i}@example.test";
        }

        $this->withHeaders($headers)
            ->postJson('/api/v1/member/referral/email-invites', ['emails' => $emails])
            ->assertOk()
            ->assertJsonPath('data.sent', 20);

        $this->assertSame(20, ClientReferralEmailSend::query()
            ->where('referrer_client_id', $referrer->id)
            ->count());

        $emails[] = 'one-more@example.test';
        $this->withHeaders($headers)
            ->postJson('/api/v1/member/referral/email-invites', ['emails' => $emails])
            ->assertStatus(422);
    }

    public function test_tenant_isolation_on_invite_code(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $referrer = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'A',
            'email' => 'a@example.test',
            'phone' => '+447700900950',
            'is_active' => true,
        ]);
        $invite = app(ClientReferralService::class)->ensureInviteForClient($referrer);

        // Other tenant join with this code must not attribute.
        $this->withHeaders(['X-Tenant-Slug' => $ctx['otherTenant']->slug])
            ->postJson('/api/v1/join/clients', $this->membershipJoinPayload([
                'preferred_name' => 'Other',
                'whatsapp_number' => '+447700900951',
                'email' => 'other@example.test',
                'referral_code' => $invite->code,
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['otherTenant']->id,
            'phone' => '+447700900951',
            'referred_by_client_id' => null,
        ]);
        $this->assertSame(0, ClientReferralConversion::withoutGlobalScopes()
            ->where('tenant_id', $ctx['otherTenant']->id)
            ->count());
        $this->assertSame(0, ClientReferralInvite::withoutGlobalScopes()
            ->where('tenant_id', $ctx['otherTenant']->id)
            ->where('code', $invite->code)
            ->count());
    }
}
