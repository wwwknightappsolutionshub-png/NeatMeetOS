<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\PlatformNotification;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformNotificationsAndModulesTest extends TestCase
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
                    'features' => [
                        'booking' => true,
                        'crm' => true,
                        'payments' => true,
                        'pos' => $plan['slug'] !== 'basic',
                        'inventory' => $plan['slug'] !== 'basic',
                        'memberships' => $plan['slug'] !== 'basic',
                        'marketing' => $plan['slug'] === 'diamond',
                        'notifications' => $plan['slug'] !== 'basic',
                        'analytics' => $plan['slug'] !== 'basic',
                        'integrations' => $plan['slug'] === 'diamond',
                        'ecommerce' => $plan['slug'] === 'diamond',
                    ],
                    'limits' => ['max_locations' => 1, 'max_staff' => 5, 'max_workspaces' => 10],
                    'is_active' => true,
                ],
            );
        }

        app(SignupFormDefinitionService::class)->ensureDefaultActive();
    }

    public function test_signup_creates_platform_notification_and_emails_admins(): void
    {
        User::factory()->create([
            'email' => 'platform-notify@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        $this->postJson('/api/v1/signup/register', [
            'answers' => [
                'business_name' => 'Bloom Hair',
                'trading_name' => 'Bloom',
                'slug' => 'bloom-hair-notify',
                'business_type' => 'boutique',
                'timezone' => 'Europe/London',
                'contact_email' => 'hello@bloom.test',
                'owner_first_name' => 'Ava',
                'owner_last_name' => 'Bloom',
                'owner_email' => 'ava-notify@bloom.test',
                'owner_whatsapp' => '+447700900123',
                'location_name' => 'Main',
                'address_line1' => '1 High Street',
                'city' => 'London',
                'postcode' => 'E1 1AA',
                'country' => 'GB',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
                'desired_plan_slug' => 'pro',
                'services' => [
                    [
                        'name' => 'Cut & Blow Dry',
                        'category' => 'hair',
                        'description' => 'Classic cut',
                        'duration_minutes' => 60,
                        'base_price_cents' => 4500,
                        'image_url' => null,
                    ],
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('platform_notifications', [
            'type' => PlatformNotification::TYPE_TENANT_SIGNUP,
        ]);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'bloom-hair-notify',
            'status' => 'pending_activation',
        ]);
    }

    public function test_platform_admin_can_list_and_mark_notifications(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-bell@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        $notification = PlatformNotification::query()->create([
            'type' => PlatformNotification::TYPE_TENANT_SIGNUP,
            'title' => 'New salon signup',
            'body' => 'Test body',
            'data' => ['href' => '/platform/tenants'],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.id', $notification->id);

        $this->postJson('/api/v1/platform/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_platform_admin_can_update_plan_and_tenant_modules(): void
    {
        $ctx = $this->seedTenantContext(['pos.view', 'identity.view']);
        $admin = User::factory()->create([
            'email' => 'platform-modules@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $plan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $this->putJson('/api/v1/platform/plans/'.$plan->id.'/modules', [
            'features' => [
                'booking' => true,
                'crm' => true,
                'payments' => true,
                'pos' => false,
                'inventory' => false,
                'memberships' => false,
                'marketing' => false,
                'notifications' => false,
                'analytics' => false,
                'integrations' => false,
                'ecommerce' => false,
            ],
            'limits' => [
                'max_locations' => 2,
                'max_staff' => 8,
                'max_workspaces' => 12,
            ],
        ])->assertOk()
            ->assertJsonPath('data.limits.max_locations', 2);

        $this->putJson('/api/v1/platform/tenants/'.$ctx['tenant']->id.'/modules', [
            'overrides' => [
                'pos' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.overrides.pos', true)
            ->assertJsonPath('data.effective.pos', true);

        Sanctum::actingAs($ctx['user']);
        $this->withHeader('X-Tenant-Slug', $ctx['tenant']->slug)
            ->getJson('/api/v1/shell')
            ->assertOk()
            ->assertJsonPath('data.features.pos', true);
    }

    public function test_disabled_module_blocks_permission_route(): void
    {
        $ctx = $this->seedTenantContext(['pos.view']);
        /** @var Tenant $tenant */
        $tenant = $ctx['tenant'];
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $tenant->forceFill(['subscription_plan_id' => $basic->id])->save();
        $basic->forceFill([
            'features' => array_merge(
                is_array($basic->features) ? $basic->features : [],
                ['pos' => false],
            ),
        ])->save();

        Sanctum::actingAs($ctx['user']);

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/admin/pos/checkouts')
            ->assertForbidden()
            ->assertJsonPath('code', 'module_upgrade_required')
            ->assertJsonPath('data.module', 'pos');
    }
}
