<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module2CrmJoinCaptureTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_public_join_requires_whatsapp_and_creates_client(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Pat',
                'last_name' => 'Walker',
            ])
            ->assertStatus(422);

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Pat',
                'last_name' => 'Walker',
                'whatsapp_number' => '+44 7700 900111',
                'email' => 'pat@example.test',
                'location_id' => $ctx['location']->id,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.created', true);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Pat',
            'email' => 'pat@example.test',
            'phone' => '+447700900111',
        ]);

        $clientId = $create->json('data.client_id');
        $this->assertDatabaseHas('client_consent_records', [
            'client_id' => $clientId,
            'consent_type' => ClientConsentRecord::TYPE_PRIVACY_CONTACT,
            'granted' => true,
            'source' => ClientConsentRecord::SOURCE_ONLINE_FORM,
        ]);
    }

    public function test_public_join_updates_existing_by_whatsapp(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Old',
            'phone' => '+447700900222',
            'is_active' => true,
        ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Old',
                'whatsapp_number' => '+44 7700 900222',
                'email' => 'old@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertEquals(1, Client::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('phone', '+447700900222')
            ->count());

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'phone' => '+447700900222',
            'email' => 'old@example.test',
        ]);
    }

    public function test_join_bootstrap_and_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/join/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.tenant.slug', $ctx['tenant']->slug)
            ->assertJsonStructure([
                'data' => [
                    'tenant',
                    'locations',
                    'offers' => ['memberships', 'packages', 'loyalty'],
                ],
            ]);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['otherTenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Other',
                'whatsapp_number' => '+447700900333',
            ])
            ->assertCreated();

        $this->assertDatabaseMissing('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'phone' => '+447700900333',
        ]);
        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['otherTenant']->id,
            'phone' => '+447700900333',
        ]);
    }

    public function test_join_bootstrap_includes_public_membership_and_loyalty_offers(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage', 'memberships.view', 'memberships.manage']);
        app(\App\Shared\Tenancy\TenantContext::class)->set($ctx['tenant']);

        $plan = app(\App\Domains\Memberships\Services\MembershipPlanService::class)->create([
            'name' => 'Public Club',
            'description' => 'Member pricing and wallet credit.',
            'billing_frequency' => \App\Domains\Memberships\Enums\MembershipBillingFrequency::MONTHLY,
            'price_cents' => 5000,
            'included_wallet_credit_cents' => 500,
            'is_public' => true,
        ]);

        app(\App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService::class)->update([
            'is_loyalty_redemption_enabled' => true,
            'points_per_redemption_block' => 100,
            'value_cents_per_block' => 1000,
        ]);

        $response = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/join/bootstrap');

        $response->assertOk()
            ->assertJsonPath('data.offers.memberships.0.id', $plan->id)
            ->assertJsonPath('data.offers.memberships.0.name', 'Public Club')
            ->assertJsonPath('data.offers.loyalty.enabled', true)
            ->assertJsonPath('data.offers.loyalty.points_per_redemption_block', 100);
    }
}
