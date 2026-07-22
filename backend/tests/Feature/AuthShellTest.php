<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_shell_returns_tenant_context(): void
    {
        $planId = (string) Str::uuid();
        \DB::table('subscription_plans')->insert([
            'id' => $planId,
            'name' => 'Starter',
            'slug' => 'starter',
            'features' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Test Salon',
            'slug' => 'test-salon',
            'status' => 'active',
            'subscription_plan_id' => $planId,
        ]);

        $user = User::factory()->create([
            'email' => 'owner@test.local',
            'password' => Hash::make('password'),
        ]);

        TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Owner',
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant-Slug', 'test-salon')
            ->getJson('/api/v1/shell');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.tenant.slug', 'test-salon')
            ->assertJsonPath('data.trial.active', true)
            ->assertJsonPath('data.trial.day', 1)
            ->assertJsonPath('data.trial.total_days', 30)
            ->assertJsonPath('data.trial.label', 'You are on Day 1 / 30');
    }
}
