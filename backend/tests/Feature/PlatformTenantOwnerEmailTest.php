<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformTenantOwnerEmailTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function actingAsPlatformManager(): User
    {
        $user = User::query()->create([
            'name' => 'Platform Manager Email',
            'email' => 'platform.manager.email@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'manager',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_manager_can_change_tenant_owner_email(): void
    {
        $this->actingAsPlatformManager();
        $ctx = $this->seedTenantContext(['identity.view']);
        $ctx['tenant']->forceFill(['contact_email' => 'owner@test.local'])->save();

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-email', [
            'email' => 'new.owner@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_email', 'new.owner@example.test')
            ->assertJsonPath('data.contact_email', 'new.owner@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $ctx['user']->id,
            'email' => 'new.owner@example.test',
        ]);
        $this->assertDatabaseHas('tenants', [
            'id' => $ctx['tenant']->id,
            'contact_email' => 'new.owner@example.test',
        ]);

        $this->postJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-email', [
            'email' => 'post.owner@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_email', 'post.owner@example.test');
    }

    public function test_cannot_reuse_another_account_email(): void
    {
        $this->actingAsPlatformManager();
        $ctx = $this->seedTenantContext(['identity.view']);

        User::query()->create([
            'name' => 'Taken',
            'email' => 'taken@example.test',
            'password' => Hash::make('password'),
        ]);

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-email', [
            'email' => 'taken@example.test',
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $ctx['user']->id,
            'email' => 'owner@test.local',
        ]);
    }

    public function test_support_role_cannot_change_owner_email(): void
    {
        $support = User::query()->create([
            'name' => 'Platform Support',
            'email' => 'platform.support.email@example.test',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'support',
        ]);
        Sanctum::actingAs($support);

        $ctx = $this->seedTenantContext(['identity.view']);

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/owner-email', [
            'email' => 'blocked@example.test',
        ])->assertForbidden();
    }
}
