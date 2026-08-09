<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformTenantPurgeTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function actingAsPlatformOwner(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Owner Purge',
            'email' => 'platform.purge@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_owner_can_permanently_purge_tenant(): void
    {
        $this->actingAsPlatformOwner();
        $ctx = $this->seedTenantContext(['identity.view']);
        $tenantId = $ctx['tenant']->id;
        $userId = $ctx['user']->id;

        $this->postJson('/api/v1/platform/tenants/'.$tenantId.'/purge', [
            'confirmation_slug' => 'wrong-slug',
            'confirm' => true,
        ])->assertStatus(422);

        $this->postJson('/api/v1/platform/tenants/'.$tenantId.'/purge', [
            'confirmation_slug' => $ctx['tenant']->slug,
            'confirm' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.purged', true)
            ->assertJsonPath('data.slug', $ctx['tenant']->slug);

        $this->assertDatabaseMissing('tenants', ['id' => $tenantId]);
        $this->assertDatabaseMissing('team_members', ['tenant_id' => $tenantId]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertNull(Tenant::query()->find($tenantId));
    }

    public function test_manager_cannot_purge_tenant(): void
    {
        $manager = User::query()->create([
            'name' => 'Platform Manager',
            'email' => 'platform.manager.purge@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'manager',
        ]);
        Sanctum::actingAs($manager);

        $ctx = $this->seedTenantContext(['identity.view']);

        $this->postJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/purge', [
            'confirmation_slug' => $ctx['tenant']->slug,
            'confirm' => true,
        ])->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $ctx['tenant']->id]);
        $this->assertTrue(
            TeamMember::withoutGlobalScopes()->where('tenant_id', $ctx['tenant']->id)->exists(),
        );
    }
}
