<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformStaffAndProfileTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    private function makeOwner(string $email = 'owner-platform@neatmeet.local'): User
    {
        return User::factory()->create([
            'name' => 'Platform Owner',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => PlatformRole::OWNER,
        ]);
    }

    public function test_owner_can_update_profile_and_password(): void
    {
        $owner = $this->makeOwner();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/platform/profile', [
            'name' => 'Owner Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Owner Updated')
            ->assertJsonPath('data.user.platform_role', 'owner');

        $this->putJson('/api/v1/platform/profile/password', [
            'current_password' => 'password',
            'password' => 'NewPassword99',
            'password_confirmation' => 'NewPassword99',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword99', $owner->fresh()->password));
    }

    public function test_owner_can_create_manager_and_support_cannot_manage_staff(): void
    {
        $this->seedTenantContext(['identity.view']);
        $owner = $this->makeOwner();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/v1/platform/staff', [
            'name' => 'Ops Manager',
            'email' => 'manager@neatmeet.local',
            'password' => 'ManagerPass99',
            'platform_role' => PlatformRole::MANAGER,
        ])->assertCreated();

        $managerId = $create->json('data.id');
        $this->assertNotNull($managerId);

        $manager = User::query()->findOrFail($managerId);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/platform/tenants')->assertOk();
        $this->getJson('/api/v1/platform/staff')->assertForbidden();
        $this->postJson('/api/v1/platform/staff', [
            'name' => 'Another',
            'email' => 'another@neatmeet.local',
            'password' => 'SupportPass99',
            'platform_role' => PlatformRole::SUPPORT,
        ])->assertForbidden();
    }

    public function test_support_cannot_mutate_tenants(): void
    {
        $ctx = $this->seedTenantContext(['identity.view']);
        $support = User::factory()->create([
            'email' => 'support@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => PlatformRole::SUPPORT,
        ]);

        Sanctum::actingAs($support);

        $this->getJson('/api/v1/platform/tenants')->assertOk();
        $this->postJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/suspend')
            ->assertForbidden();
    }

    public function test_login_and_shell_expose_platform_role(): void
    {
        $this->makeOwner('shell-owner@neatmeet.local');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'shell-owner@neatmeet.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.platform_role', 'owner');

        $user = User::query()->where('email', 'shell-owner@neatmeet.local')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonPath('data.user.platform_role', 'owner');
    }
}
