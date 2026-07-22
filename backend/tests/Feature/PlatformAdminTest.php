<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_platform_routes_require_platform_admin(): void
    {
        $ctx = $this->seedTenantContext(['identity.view']);

        Sanctum::actingAs($ctx['user']);

        $this->getJson('/api/v1/platform/overview')
            ->assertForbidden();

        $this->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }

    public function test_platform_admin_can_list_tenants_and_overview(): void
    {
        $ctx = $this->seedTenantContext(['identity.view']);

        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-test@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/overview')
            ->assertOk()
            ->assertJsonPath('data.tenants_total', 2)
            ->assertJsonStructure([
                'data' => [
                    'tenants_total',
                    'tenants_active',
                    'users_total',
                    'appointments_last_7d',
                    'payments_collected_last_7d_cents',
                ],
            ]);

        $tenants = $this->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($tenants);
        $this->assertGreaterThanOrEqual(2, count($tenants));
        $this->assertTrue(collect($tenants)->contains(fn ($t) => $t['slug'] === $ctx['tenant']->slug));
    }

    public function test_login_exposes_platform_admin_flag(): void
    {
        User::factory()->create([
            'email' => 'platform-login@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'platform-login@neatmeet.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.is_platform_admin', true);
    }
}
