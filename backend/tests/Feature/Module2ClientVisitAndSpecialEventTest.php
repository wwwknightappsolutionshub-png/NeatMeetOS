<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module2ClientVisitAndSpecialEventTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_join_with_special_event_fields(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage']);
        $salonName = $ctx['tenant']->trading_name ?: $ctx['tenant']->name;
        $expectedMessage = 'Thank you so much for joining "'.$salonName.'". We are excited about your decision. Check your email for more details about our membership reward and how to install and use our membership app.';

        $create = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Ava',
                'last_name' => 'Lane',
                'whatsapp_number' => '+447700900701',
                'email' => 'ava@example.test',
                'location_id' => $ctx['location']->id,
                'special_event_month' => 7,
                'special_event_day' => 20,
                'special_event_label' => 'Anniversary',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.message', $expectedMessage);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'email' => 'ava@example.test',
            'special_event_month' => 7,
            'special_event_day' => 20,
            'special_event_label' => 'Anniversary',
        ]);

        $this->assertDatabaseHas('client_loyalty_entries', [
            'tenant_id' => $ctx['tenant']->id,
            'client_id' => $create->json('data.client_id'),
            'entry_type' => LoyaltyEntryType::CRM_JOIN_SIGNUP,
            'points' => 300,
        ]);

        $update = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/join/clients', [
                'first_name' => 'Ava',
                'whatsapp_number' => '+44 7700 900701',
                'special_event_month' => 12,
                'special_event_day' => 25,
                'special_event_label' => 'Birthday',
            ]);

        $update->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.message', $expectedMessage);

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $ctx['tenant']->id,
            'phone' => '+447700900701',
            'special_event_month' => 12,
            'special_event_day' => 25,
            'special_event_label' => 'Birthday',
        ]);
    }

    public function test_member_check_in_awards_points_once_per_day(): void
    {
        $ctx = $this->seedTenantContext(['crm.view', 'crm.manage', 'memberships.view', 'memberships.manage']);
        app(TenantContext::class)->set($ctx['tenant']);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Visit',
            'last_name' => 'Member',
            'email' => 'visit@example.test',
            'phone' => '+447700900702',
            'is_active' => true,
            'primary_location_id' => $ctx['location']->id,
        ]);

        $login = $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->postJson('/api/v1/member/login', [
                'email' => 'visit@example.test',
                'phone' => '+447700900702',
            ]);
        $login->assertOk();
        $token = $login->json('data.token');

        $headers = [
            'X-Tenant-Slug' => $ctx['tenant']->slug,
            'Authorization' => 'Bearer '.$token,
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-in', [
                'location_id' => $ctx['location']->id,
            ]);

        $first->assertOk()
            ->assertJsonPath('data.already_checked_in_today', false)
            ->assertJsonPath('data.points', 10)
            ->assertJsonPath('data.visit.loyalty_points_awarded', 10);

        $this->assertEquals(1, ClientVisit::withoutGlobalScopes()->where('client_id', $client->id)->count());
        $this->assertEquals(10, app(LoyaltyLedgerService::class)->balanceForClient($client->id));
        $this->assertDatabaseHas('client_loyalty_entries', [
            'client_id' => $client->id,
            'entry_type' => LoyaltyEntryType::CHECKIN_VISIT,
            'points' => 10,
        ]);
        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_VISIT_CHECKIN,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'visit.checkin']);

        $client->refresh();
        $this->assertNotNull($client->last_visited_at);

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/member/check-in', [
                'location_id' => $ctx['location']->id,
            ]);

        $second->assertOk()
            ->assertJsonPath('data.already_checked_in_today', true)
            ->assertJsonPath('data.points', 0);

        $this->assertEquals(1, ClientVisit::withoutGlobalScopes()->where('client_id', $client->id)->count());
        $this->assertEquals(1, ClientLoyaltyEntry::withoutGlobalScopes()->where('client_id', $client->id)->count());
        $this->assertEquals(10, app(LoyaltyLedgerService::class)->balanceForClient($client->id));

        $this->withHeaders($headers)
            ->getJson('/api/v1/member/visit-status')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', true);

        $this->withHeaders($headers)
            ->getJson('/api/v1/member/me')
            ->assertOk()
            ->assertJsonPath('data.checked_in_today', true)
            ->assertJsonPath('data.loyalty_points_balance', 10);

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/member/bootstrap')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'locations' => [
                        ['id', 'name', 'latitude', 'longitude', 'geofence_radius_meters'],
                    ],
                ],
            ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/visits')
            ->assertOk()
            ->assertJsonPath('data.0.loyalty_points_awarded', 10);
    }
}
