<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\AuditLog;
use App\Domains\Identity\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module1BAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_branding_can_be_retrieved_and_updated(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/branding')
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#18181b');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/branding', [
                'brand_display_name' => 'Test Brand',
                'primary_color' => '#ff0000',
                'support_email' => 'support@test.local',
            ])
            ->assertOk()
            ->assertJsonPath('data.brand_display_name', 'Test Brand')
            ->assertJsonPath('data.primary_color', '#ff0000');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $ctx['tenant']->id,
            'action' => 'branding.updated',
        ]);
    }

    public function test_roles_can_be_created_updated_and_permissions_assigned(): void
    {
        $ctx = $this->seedTenantContext();

        $create = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/roles', [
                'name' => 'Stylist',
                'slug' => 'stylist',
                'permission_ids' => ['identity.view', 'booking.view'],
            ]);

        $create->assertCreated();
        $roleId = $create->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/roles/'.$roleId.'/permissions', [
                'permission_ids' => ['identity.view', 'booking.view', 'booking.manage'],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['identity.view', 'booking.view', 'booking.manage'],
            $this->withTenantAuth($ctx['token'])
                ->getJson('/api/v1/admin/roles/'.$roleId)
                ->json('data.permission_ids'),
        );

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/roles/'.$roleId, ['name' => 'Senior Stylist'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Senior Stylist');

        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_updated']);
    }

    public function test_system_role_cannot_be_archived(): void
    {
        $ctx = $this->seedTenantContext();

        $ownerRoleId = Role::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('slug', 'owner')
            ->value('id');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/roles/'.$ownerRoleId.'/archive')
            ->assertStatus(422);
    }

    public function test_role_management_respects_tenant_isolation(): void
    {
        $ctx = $this->seedTenantContext();

        $foreignRole = Role::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
            'is_active' => true,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/roles/'.$foreignRole->id)
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/roles/'.$foreignRole->id, ['name' => 'Hacked'])
            ->assertStatus(422);
    }

    public function test_viewer_cannot_manage_roles_without_access_permission(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['viewerToken'])
            ->postJson('/api/v1/admin/roles', ['name' => 'Bad Role'])
            ->assertForbidden();

        $this->withTenantAuth($ctx['viewerToken'])
            ->getJson('/api/v1/admin/permissions')
            ->assertOk();
    }

    public function test_subscription_visibility_returns_current_plan(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.slug', $ctx['plan']->slug);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/subscription/plans')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'limits']]]);
    }

    public function test_audit_logs_are_platform_only(): void
    {
        $ctx = $this->seedTenantContext();

        AuditLog::withoutGlobalScopes()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $ctx['tenant']->id,
            'action' => 'location.created',
            'entity_type' => 'App\\Domains\\Identity\\Models\\Location',
            'created_at' => now(),
        ]);

        AuditLog::withoutGlobalScopes()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $ctx['otherTenant']->id,
            'action' => 'location.created',
            'created_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/audit-logs')
            ->assertNotFound();

        $admin = \App\Domains\Identity\Models\User::factory()->create([
            'email' => 'platform-audit@neatmeet.local',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/platform/audit-logs?action=location.created')
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, count($response->json('data.items')));

        $filtered = $this->getJson('/api/v1/platform/audit-logs?tenant_id='.$ctx['tenant']->id)
            ->assertOk()
            ->json('data.items');

        $this->assertTrue(collect($filtered)->every(
            fn ($row) => $row['tenant_id'] === $ctx['tenant']->id,
        ));
    }
}
