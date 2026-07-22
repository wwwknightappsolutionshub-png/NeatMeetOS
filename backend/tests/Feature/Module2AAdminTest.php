<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTag;
use App\Domains\Crm\Models\ClientTimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module2AAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_client_crud_with_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients', [
                'first_name' => 'Sam',
                'last_name' => 'Client',
                'email' => 'sam@client.test',
                'phone' => '+4400111222333',
                'primary_location_id' => $ctx['location']->id,
            ]);

        $create->assertCreated();
        $clientId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$clientId)
            ->assertOk()
            ->assertJsonPath('data.email', 'sam@client.test');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/clients/'.$clientId, ['phone' => '+4400999888777'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+4400999888777');

        Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Foreign',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients?search=Foreign')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created']);
        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $clientId,
            'event_type' => ClientTimelineEvent::EVENT_CLIENT_CREATED,
        ]);
    }

    public function test_client_search_and_tag_filter(): void
    {
        $ctx = $this->seedTenantContext();

        $tag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'VIP',
            'slug' => 'vip',
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Tagged',
            'last_name' => 'Person',
            'email' => 'tagged@test.local',
            'is_active' => true,
        ]);
        $client->tags()->attach($tag->id);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients?search=Tagged')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients?tag_ids='.$tag->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_tags_can_be_assigned_to_client(): void
    {
        $ctx = $this->seedTenantContext();

        $tag = ClientTag::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Regular',
            'slug' => 'regular',
        ]);

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Tag',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/clients/'.$client->id.'/tags', [
                'tag_ids' => [$tag->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.tag_ids', [$tag->id]);

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_TAG_ASSIGNED,
        ]);
    }

    public function test_notes_and_consent_history(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Note',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/notes', [
                'body' => 'Called to confirm appointment.',
                'note_type' => 'follow_up',
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/consents', [
                'consent_type' => 'marketing_email',
                'granted' => true,
                'source' => 'in_person',
            ])
            ->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/consents')
            ->assertOk()
            ->assertJsonPath('data.current.marketing_email.granted', true)
            ->assertJsonCount(1, 'data.history');

        $this->assertDatabaseHas('audit_logs', ['action' => 'client.note_added']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.consent_updated']);
    }

    public function test_timeline_lists_client_events(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Timeline',
            'last_name' => 'Test',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/notes', [
                'body' => 'Timeline note',
            ])
            ->assertCreated();

        $response = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/timeline')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, count($response->json('data.items')));
    }

    public function test_viewer_cannot_mutate_crm_without_manage_permission(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/clients', ['first_name' => 'Hacker'])
            ->assertForbidden();

        $this->withTenantAuth($ctx['viewerToken'])
            ->getJson('/api/v1/admin/clients')
            ->assertForbidden();
    }

    public function test_crm_viewer_role_can_read_but_not_write(): void
    {
        $ctx = $this->seedTenantContext();

        $crmViewer = \App\Domains\Identity\Models\User::factory()->create([
            'email' => 'crmviewer@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $crmViewerMember = \App\Domains\Identity\Models\TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $crmViewer->id,
            'employment_type' => \App\Domains\Identity\Models\TeamMember::EMPLOYMENT_EMPLOYEE,
            'display_name' => 'CRM Viewer',
            'is_active' => true,
        ]);

        $crmViewerRole = \App\Domains\Identity\Models\Role::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'CRM Viewer',
            'slug' => 'crm-viewer',
            'is_active' => true,
        ]);
        $crmViewerRole->permissions()->sync(['crm.view']);
        $crmViewerMember->roles()->attach($crmViewerRole->id);

        $viewerToken = $crmViewer->createToken('crm-viewer')->plainTextToken;

        $this->withTenantAuth($viewerToken)
            ->getJson('/api/v1/admin/clients')
            ->assertOk();

        $this->withTenantAuth($viewerToken)
            ->postJson('/api/v1/admin/clients', ['first_name' => 'Nope'])
            ->assertForbidden();
    }
}
