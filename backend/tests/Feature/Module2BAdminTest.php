<?php

namespace Tests\Feature;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientFormula;
use App\Domains\Crm\Models\ClientTimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module2BAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_formula_crud_with_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Formula',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/formulas', [
                'title' => 'Balayage mix',
                'formula_body' => '7.1 + 8.0, 20 vol',
                'category' => 'colour',
                'service_context' => 'Balayage',
            ]);

        $create->assertCreated();
        $formulaId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/clients/'.$client->id.'/formulas/'.$formulaId, [
                'title' => 'Balayage mix v2',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Balayage mix v2');

        $foreignClient = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'first_name' => 'Foreign',
            'is_active' => true,
        ]);

        $foreignFormula = ClientFormula::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'client_id' => $foreignClient->id,
            'title' => 'Secret',
            'formula_body' => 'x',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$foreignClient->id.'/formulas/'.$foreignFormula->id)
            ->assertNotFound();

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_FORMULA_CREATED,
        ]);
    }

    public function test_photo_and_document_registration(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Asset',
            'last_name' => 'Client',
            'is_active' => true,
        ]);

        $photo = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/photos', [
                'storage_path' => '/storage/tenant/demo/photo.jpg',
                'category' => 'reference',
                'caption' => 'Inspo',
            ]);
        $photo->assertCreated();
        $photoId = $photo->json('data.id');

        $doc = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/documents', [
                'title' => 'Consent form',
                'document_type' => 'signed',
                'storage_path' => '/storage/tenant/demo/consent.pdf',
            ]);
        $doc->assertCreated();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/clients/'.$client->id.'/photos')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/clients/'.$client->id.'/photos/'.$photoId.'/archive')
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'client.photo_added']);
        $this->assertDatabaseHas('client_timeline_events', [
            'event_type' => ClientTimelineEvent::EVENT_DOCUMENT_ADDED,
        ]);
    }

    public function test_viewer_cannot_create_formula(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Locked',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/clients/'.$client->id.'/formulas', [
                'title' => 'Nope',
                'formula_body' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_client_profile_enrichment_fields(): void
    {
        $ctx = $this->seedTenantContext();

        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Rich',
            'last_name' => 'Profile',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/clients/'.$client->id, [
                'preferred_team_member_id' => $ctx['teamMember']->id,
                'preferences' => ['communication_channel' => 'sms'],
                'loyalty_display_status' => 'none',
            ])
            ->assertOk()
            ->assertJsonPath('data.preferences.communication_channel', 'sms');

        $this->assertDatabaseHas('client_timeline_events', [
            'client_id' => $client->id,
            'event_type' => ClientTimelineEvent::EVENT_PROFILE_PREFERENCES_UPDATED,
        ]);
    }
}
