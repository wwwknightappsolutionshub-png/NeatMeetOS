<?php

namespace Tests\Feature;

use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleMediumGapFixesTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_stale_generating_can_be_retried(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Config::set('ai_hairstyle.stale_generating_minutes', 1);

        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-stale@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk();

        $create = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated();
        $sessionId = $create->json('data.id');
        $token = $create->json('data.public_token');

        $session = AiHairstyleSession::withoutGlobalScopes()->findOrFail($sessionId);
        $session->forceFill([
            'status' => AiHairstyleStatuses::SESSION_GENERATING,
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->post('/api/v1/book/ai-hairstyle/sessions/'.$sessionId.'/generate', [
                'public_token' => $token,
                'selfie' => UploadedFile::fake()->image('retry.jpg', 220, 280),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', AiHairstyleStatuses::SESSION_READY);
    }

    public function test_fresh_generating_rejects_retry(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Config::set('ai_hairstyle.stale_generating_minutes', 7);

        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-fresh-gen@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);
        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk();

        $create = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->postJson('/api/v1/book/ai-hairstyle/sessions')
            ->assertCreated();

        $session = AiHairstyleSession::withoutGlobalScopes()->findOrFail($create->json('data.id'));
        $session->forceFill([
            'status' => AiHairstyleStatuses::SESSION_GENERATING,
            'updated_at' => now(),
        ])->save();

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->post('/api/v1/book/ai-hairstyle/sessions/'.$session->id.'/generate', [
                'public_token' => $create->json('data.public_token'),
                'selfie' => UploadedFile::fake()->image('early.jpg', 200, 200),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_shell_includes_ai_hairstyle_permissions_for_owner(): void
    {
        $ctx = $this->seedTenantContext(['ai_hairstyle.view', 'ai_hairstyle.manage']);

        Sanctum::actingAs($ctx['user']);
        $this->withTenantAuth($ctx['token'], $ctx['tenant']->slug)
            ->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonFragment(['ai_hairstyle.view'])
            ->assertJsonFragment(['ai_hairstyle.manage']);
    }

    public function test_manager_roles_can_receive_ai_hairstyle_permissions(): void
    {
        foreach (['ai_hairstyle.view', 'ai_hairstyle.manage'] as $id) {
            Permission::query()->firstOrCreate(
                ['id' => $id],
                ['name' => $id, 'slug' => $id, 'module' => 'ai_hairstyle'],
            );
        }

        $ctx = $this->seedTenantContext();
        $manager = Role::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'is_system' => false,
            'is_active' => true,
        ]);

        Role::withoutGlobalScopes()
            ->whereIn('slug', ['owner', 'manager'])
            ->orderBy('id')
            ->each(function (Role $role) {
                $role->permissions()->syncWithoutDetaching([
                    'ai_hairstyle.view',
                    'ai_hairstyle.manage',
                ]);
            });

        $ids = $manager->fresh()->permissions()->pluck('permissions.id')->all();
        $this->assertContains('ai_hairstyle.view', $ids);
        $this->assertContains('ai_hairstyle.manage', $ids);
    }
}
