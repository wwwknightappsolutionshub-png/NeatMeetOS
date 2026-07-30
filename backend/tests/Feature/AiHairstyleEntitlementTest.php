<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Identity\Support\PlatformModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class AiHairstyleEntitlementTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_catalogue_includes_ai_hairstyle_and_plans_default_off(): void
    {
        $this->assertTrue(PlatformModuleCatalogue::isValid('ai_hairstyle'));

        foreach (['basic', 'pro', 'diamond'] as $slug) {
            $features = PlatformModuleCatalogue::defaultsForPlanSlug($slug);
            $this->assertFalse($features['ai_hairstyle'], "Expected ai_hairstyle off for {$slug}");
        }
    }

    public function test_ineligible_tenant_stays_off_even_with_override(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'other'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-ineligible@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertStatus(422);

        $features = app(TenantEntitlementService::class)->resolveFeatures($tenant->fresh());
        $this->assertFalse($features['ai_hairstyle']);
    }

    public function test_eligible_override_within_trial_enables_feature_on_shell(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'barbershop'])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-enable@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk()
            ->assertJsonPath('data.overrides.ai_hairstyle', true)
            ->assertJsonPath('data.effective.ai_hairstyle', true)
            ->assertJsonPath('data.ai_hairstyle_eligible', true);

        $tenant->refresh();
        $this->assertNotNull($tenant->ai_hairstyle_trial_ends_at);
        $this->assertTrue($tenant->ai_hairstyle_trial_ends_at->isFuture());

        Sanctum::actingAs($ctx['user']);
        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonPath('data.features.ai_hairstyle', true);
    }

    public function test_expired_trial_disables_feature_despite_override(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $tenant->forceFill([
            'business_type' => 'boutique',
            'ai_hairstyle_trial_ends_at' => now()->subDay(),
        ])->save();

        $admin = User::factory()->create([
            'email' => 'platform-ai-expired@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        // Force-on with expired trial renews the window (super-admin re-enable).
        $this->putJson('/api/v1/platform/tenants/'.$tenant->id.'/modules', [
            'overrides' => ['ai_hairstyle' => true],
        ])->assertOk()
            ->assertJsonPath('data.effective.ai_hairstyle', true);

        $tenant->refresh();
        $this->assertTrue($tenant->ai_hairstyle_trial_ends_at->isFuture());

        // Simulate expiry without clearing override.
        $tenant->forceFill(['ai_hairstyle_trial_ends_at' => now()->subMinute()])->save();

        $features = app(TenantEntitlementService::class)->resolveFeatures($tenant->fresh());
        $this->assertFalse($features['ai_hairstyle']);

        Sanctum::actingAs($ctx['user']);
        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonPath('data.features.ai_hairstyle', false);
    }

    public function test_plan_features_alone_do_not_enable_ai_hairstyle(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $tenant->forceFill(['business_type' => 'spa'])->save();

        $plan = SubscriptionPlan::query()->findOrFail($tenant->subscription_plan_id);
        $plan->forceFill([
            'features' => array_merge(
                is_array($plan->features) ? $plan->features : [],
                ['ai_hairstyle' => true],
            ),
        ])->save();

        $features = app(TenantEntitlementService::class)->resolveFeatures($tenant->fresh());
        $this->assertFalse($features['ai_hairstyle']);
    }
}
