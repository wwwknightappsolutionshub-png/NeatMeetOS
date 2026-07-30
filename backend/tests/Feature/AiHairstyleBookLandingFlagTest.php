<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\TenantModuleOverride;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleBookLandingFlagTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_catalog_ai_hairstyle_landing_false_by_default(): void
    {
        $ctx = $this->seedTenantContext();
        $ctx['tenant']->forceFill(['business_type' => 'barbershop'])->save();

        $this->withHeaders(['X-Tenant-Slug' => $ctx['tenant']->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.ai_hairstyle_landing', false);
    }

    public function test_catalog_ai_hairstyle_landing_true_when_entitled(): void
    {
        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'boutique'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-landing@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk()
            ->assertJsonPath('data.effective.ai_hairstyle', true);

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.ai_hairstyle_landing', true);
    }

    public function test_catalog_ai_hairstyle_landing_false_after_trial_expires(): void
    {
        $ctx = $this->seedTenantContext();
        $tenant = $ctx['tenant'];
        $tenant->forceFill([
            'business_type' => 'spa',
            'ai_hairstyle_trial_ends_at' => now()->addDays(30),
        ])->save();

        TenantModuleOverride::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'module_key' => 'ai_hairstyle',
            'enabled' => true,
        ]);

        $this->assertTrue(app(TenantEntitlementService::class)->isEnabled($tenant->fresh(), 'ai_hairstyle'));

        $tenant->forceFill(['ai_hairstyle_trial_ends_at' => now()->subMinute()])->save();

        $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])
            ->getJson('/api/v1/book/catalog')
            ->assertOk()
            ->assertJsonPath('data.ai_hairstyle_landing', false);
    }
}
