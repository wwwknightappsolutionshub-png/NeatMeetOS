<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\PlatformReferralConversion;
use App\Domains\Identity\Models\PlatformReferralSetting;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\PlatformReferralProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class PlatformBroadcastReferralAndHeroTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_tenant_can_upload_booking_hero_image(): void
    {
        Storage::fake('public');
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->post('/api/v1/admin/branding/upload-hero', [
                'image' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/branding')
            ->assertOk()
            ->assertJsonPath('success', true);

        $branding = $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/branding')
            ->json('data');

        $this->assertNotEmpty($branding['hero_image_url'] ?? null);
    }

    public function test_platform_admin_can_broadcast_to_one_tenant(): void
    {
        $ctx = $this->seedTenantContext();
        $admin = User::factory()->create([
            'email' => 'platform-bc@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/platform/broadcasts', [
            'title' => 'Reminder',
            'body' => 'Please review your booking link.',
            'tenant_id' => $ctx['tenant']->id,
            'send_email' => true,
            'send_push' => false,
            'href' => '/admin/settings/branding',
        ])
            ->assertCreated()
            ->assertJsonPath('data.notices', 1)
            ->assertJsonPath('data.tenants', 1);

        $this->assertDatabaseHas('tenant_owner_notices', [
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['user']->id,
            'type' => 'platform.broadcast',
            'title' => 'Reminder',
        ]);
    }

    public function test_platform_referral_settings_and_reward_on_activation(): void
    {
        $ctx = $this->seedTenantContext();
        $admin = User::factory()->create([
            'email' => 'platform-ref@neatmeet.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/platform/referral-settings', [
            'enabled' => true,
            'reward_type' => PlatformReferralSetting::REWARD_ACCOUNT_CREDIT,
            'reward_amount' => 7500,
            'qualification_goal' => PlatformReferralSetting::GOAL_TENANT_ACTIVATED,
            'share_headline' => 'Invite a salon',
            'share_body' => 'Earn credit when they activate.',
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.reward_amount', 7500);

        $program = app(PlatformReferralProgramService::class);
        $invite = $program->ensureInviteForTenant($ctx['tenant']);

        $referred = Tenant::query()->create([
            'name' => 'Referred Salon',
            'slug' => 'referred-salon',
            'status' => 'pending_activation',
            'subscription_plan_id' => $ctx['plan']->id,
            'settings' => [],
        ]);

        $conversion = $program->attachOnSignup($referred, $invite->code);
        $this->assertNotNull($conversion);
        $this->assertSame(PlatformReferralConversion::STATUS_PENDING, $conversion->status);

        $referred->forceFill([
            'status' => 'active',
            'activated_at' => now(),
        ])->save();
        $program->handleTenantActivated($referred);

        $this->assertDatabaseHas('platform_referral_conversions', [
            'referred_tenant_id' => $referred->id,
            'status' => PlatformReferralConversion::STATUS_REWARDED,
            'reward_amount' => 7500,
        ]);

        $ctx['tenant']->refresh();
        $this->assertSame(7500, (int) ($ctx['tenant']->settings['platform_referral_credit_cents'] ?? 0));
    }

    public function test_owner_can_save_push_subscription(): void
    {
        $ctx = $this->seedTenantContext();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/owner-push-subscriptions', [
                'endpoint' => 'https://push.example/endpoint-'.uniqid(),
                'keys' => [
                    'p256dh' => 'dGVzdC1wMjU2ZGg=',
                    'auth' => 'dGVzdC1hdXRo',
                ],
            ])
            ->assertCreated();

        $this->assertSame(1, TenantOwnerPushSubscription::withoutGlobalScopes()
            ->where('tenant_id', $ctx['tenant']->id)
            ->where('user_id', $ctx['user']->id)
            ->count());
    }
}
