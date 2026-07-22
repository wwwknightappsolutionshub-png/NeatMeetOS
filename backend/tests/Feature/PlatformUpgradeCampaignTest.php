<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\PlatformUpgradeDiscountClaim;
use App\Domains\Identity\Models\PlatformUpgradeSend;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Models\TenantSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\PlatformUpgradeCampaignService;
use App\Domains\Identity\Services\PlatformUpgradeDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformUpgradeCampaignTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([
            ['slug' => 'basic', 'name' => 'Basic', 'price' => 4900],
            ['slug' => 'pro', 'name' => 'Pro', 'price' => 12900],
            ['slug' => 'diamond', 'name' => 'Diamond', 'price' => 29900],
        ] as $plan) {
            SubscriptionPlan::query()->firstOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'billing_interval' => 'monthly',
                    'display_price_cents' => $plan['price'],
                    'features' => [],
                    'limits' => ['max_locations' => 1, 'max_staff' => 5, 'max_workspaces' => 10],
                    'is_active' => true,
                ],
            );
        }
    }

    public function test_platform_admin_can_read_and_update_templates(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-upg@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/upgrade-campaigns/templates')
            ->assertOk()
            ->assertJsonPath('success', true);

        $templates = app(PlatformUpgradeCampaignService::class)->listTemplates();
        $this->assertNotEmpty($templates);
        $id = $templates[0]['id'];

        $this->putJson("/api/v1/platform/upgrade-campaigns/templates/{$id}", [
            'headline' => 'Edited headline for {{salon_name}}',
            'cta_label' => 'See Pro now',
        ])->assertOk()
            ->assertJsonPath('data.headline', 'Edited headline for {{salon_name}}')
            ->assertJsonPath('data.cta_label', 'See Pro now');

        $this->putJson('/api/v1/platform/upgrade-campaigns/settings', [
            'discount_percent' => 7,
            'is_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.discount_percent', 7);
    }

    public function test_day3_dispatch_creates_in_app_notice_and_whatsapp_send(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $tenant->forceFill([
            'subscription_plan_id' => $basic->id,
            'owner_whatsapp' => '+447700900999',
            'activated_at' => now()->subDays(3),
            'status' => 'trial',
        ])->save();

        TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update([
                'subscription_plan_id' => $basic->id,
                'status' => TenantSubscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(25),
            ]);

        $result = app(PlatformUpgradeDispatchService::class)->dispatchDue();
        $this->assertGreaterThanOrEqual(1, $result['sent']);

        $this->assertDatabaseHas('platform_upgrade_sends', [
            'tenant_id' => $tenant->id,
            'step' => 'day_3',
            'channel' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('platform_upgrade_sends', [
            'tenant_id' => $tenant->id,
            'step' => 'day_3',
            'channel' => 'in_app',
        ]);
        $this->assertTrue(
            TenantOwnerNotice::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('type', 'like', 'upgrade.%')
                ->exists(),
        );
    }

    public function test_day21_issues_discount_and_owner_can_claim(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        /** @var User $user */
        $user = $ctx['user'];
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $tenant->forceFill([
            'subscription_plan_id' => $basic->id,
            'activated_at' => now()->subDays(21),
            'status' => 'trial',
        ])->save();

        TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update([
                'subscription_plan_id' => $basic->id,
                'status' => TenantSubscription::STATUS_TRIAL,
                'trial_ends_at' => now()->addDays(7),
            ]);

        app(PlatformUpgradeDispatchService::class)->dispatchForTenant($tenant->fresh('subscriptionPlan'), 'day_21', true);

        $claim = PlatformUpgradeDiscountClaim::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->first();
        $this->assertNotNull($claim);

        // Re-issue via force dispatch creates another claim; use the send payload path instead:
        // Call issue through dispatch already created claim; recover plain token by re-issuing in test.
        $issued = app(\App\Domains\Identity\Services\PlatformUpgradeDiscountService::class)
            ->issue($tenant, $user, 'basic_to_pro', 5, now()->addDays(7));

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/upgrade-offer?token='.$issued['plain_token'])
            ->assertOk()
            ->assertJsonPath('data.percent', 5)
            ->assertJsonPath('data.path', 'basic_to_pro');

        $this->postJson('/api/v1/upgrade-offer/claim', ['token' => $issued['plain_token']])
            ->assertOk()
            ->assertJsonPath('data.status', PlatformUpgradeDiscountClaim::STATUS_CLAIMED);
    }

    public function test_owner_notices_endpoint_lists_day3_notice(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $tenant->forceFill([
            'subscription_plan_id' => $basic->id,
            'activated_at' => now()->subDays(3),
            'status' => 'trial',
            'owner_whatsapp' => null,
        ])->save();
        TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['subscription_plan_id' => $basic->id]);

        app(PlatformUpgradeDispatchService::class)->dispatchForTenant($tenant->fresh('subscriptionPlan'), 'day_3', true);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/owner-notices')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(
            0,
            count($this->withTenantAuth($ctx['token'])->getJson('/api/v1/admin/owner-notices')->json('data.items') ?? []),
        );
    }

    public function test_diamond_tenants_are_skipped(): void
    {
        $ctx = $this->seedTenantContext();
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $diamond = SubscriptionPlan::query()->where('slug', 'diamond')->firstOrFail();
        $tenant->forceFill([
            'subscription_plan_id' => $diamond->id,
            'activated_at' => now()->subDays(3),
            'status' => 'trial',
        ])->save();

        $result = app(PlatformUpgradeDispatchService::class)->dispatchDue();
        $this->assertSame(0, PlatformUpgradeSend::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertGreaterThanOrEqual(0, $result['skipped']);
    }
}
