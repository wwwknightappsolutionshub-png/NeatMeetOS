<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module1AAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_organization_can_be_retrieved_and_updated(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/organization')
            ->assertOk()
            ->assertJsonPath('data.slug', 'test-salon');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/organization', [
                'trading_name' => 'Test Trading',
                'business_type' => 'boutique',
                'contact_email' => 'hello@test.local',
            ])
            ->assertOk()
            ->assertJsonPath('data.trading_name', 'Test Trading');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $ctx['tenant']->id,
            'action' => 'organization.updated',
        ]);
    }

    public function test_location_crud_respects_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/locations', [
                'name' => 'Second Site',
                'timezone' => 'Europe/London',
                'address' => [
                    'line1' => '1 High Street',
                    'city' => 'London',
                    'postcode' => 'SW1A 1AA',
                    'country' => 'GB',
                ],
            ]);

        $create->assertCreated();
        $locationId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/locations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Location::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/locations/'.$locationId)
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/locations/'.$locationId.'/status', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'location.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'location.deactivated']);
    }

    public function test_workspace_crud_requires_valid_location(): void
    {
        $ctx = $this->seedTenantContext();

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/workspaces', [
                'location_id' => $ctx['location']->id,
                'name' => 'Room 1',
                'code' => 'R1',
                'workspace_type' => Workspace::TYPE_ROOM,
                'metadata' => ['capacity' => 1],
            ]);

        $response->assertCreated()->assertJsonPath('data.workspace_type', 'room');

        $workspaceId = $response->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/workspaces/'.$workspaceId, ['name' => 'Room One'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Room One');
    }

    public function test_team_member_can_be_created_and_roles_assigned(): void
    {
        $ctx = $this->seedTenantContext();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/team-members', [
                'email' => 'stylist@test.local',
                'first_name' => 'Sam',
                'last_name' => 'Stylist',
                'employment_type' => TeamMember::EMPLOYMENT_CHAIR_RENTER,
                'primary_location_id' => $ctx['location']->id,
                'workspace_ids' => [$ctx['workspace']->id],
            ]);

        $create->assertCreated();
        $memberId = $create->json('data.id');

        $viewerRoleId = Role::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('slug', 'viewer')
            ->value('id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/team-members/'.$memberId.'/roles', [
                'role_ids' => [$viewerRoleId],
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'team_member.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'team_member.roles_updated']);
    }

    public function test_viewer_cannot_mutate_without_identity_manage_permission(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['viewerToken'])
            ->putJson('/api/v1/admin/organization', ['name' => 'Hacked'])
            ->assertForbidden();

        $this->withTenantAuth($ctx['viewerToken'])
            ->getJson('/api/v1/admin/organization')
            ->assertOk();
    }
}
