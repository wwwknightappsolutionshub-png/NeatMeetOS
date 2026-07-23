<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\PlatformReferralConversion;
use App\Domains\Identity\Models\PlatformReferralSetting;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthMailService;
use App\Domains\Identity\Services\PlatformReferralProgramService;
use App\Domains\Identity\Services\PlatformReferralSettingService;
use App\Domains\Identity\Services\SignupFormDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class MarketingLeadSignupFunnelTest extends TestCase
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
                    'features' => ['booking' => true],
                    'limits' => ['max_locations' => 1],
                    'is_active' => true,
                ],
            );
        }

        app(SignupFormDefinitionService::class)->ensureDefaultActive();
    }

    public function test_lead_creates_provisional_user_and_emails_temp_password(): void
    {
        $captured = null;
        $mail = Mockery::mock(AuthMailService::class)->makePartial();
        $mail->shouldReceive('sendWelcomeTrial')
            ->once()
            ->andReturnUsing(function (User $user, string $plain) use (&$captured) {
                $captured = $plain;
                $this->assertNotSame('', $plain);
                $this->assertSame('sam@example.com', $user->email);
            });
        $this->app->instance(AuthMailService::class, $mail);

        $response = $this->postJson('/api/v1/signup/lead', [
            'name' => 'Sam Owner',
            'email' => 'sam@example.com',
            'referral_code' => 'DEMOAB12',
            'website' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'created');

        $loginUrl = (string) $response->json('data.login_url');
        $this->assertStringContainsString('email=sam%40example.com', $loginUrl);
        $this->assertStringContainsString('tab=signup', $loginUrl);

        $user = User::query()->where('email', 'sam@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->needsWorkspace());
        $this->assertSame('DEMOAB12', $user->signup_meta['referral_code'] ?? null);
        $this->assertNotNull($captured);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sam@example.com',
            'password' => $captured,
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace_incomplete', true)
            ->assertJsonPath('data.tenant', null);
    }

    public function test_lead_honeypot_does_not_create_user(): void
    {
        $this->postJson('/api/v1/signup/lead', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'website' => 'https://spam.test',
        ])->assertCreated();

        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_lead_existing_complete_account_returns_existing_status(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
            'workspace_status' => User::WORKSPACE_COMPLETE,
        ]);

        $this->postJson('/api/v1/signup/lead', [
            'name' => 'Taken',
            'email' => 'taken@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'existing');
    }

    public function test_provisional_login_then_complete_workspace(): void
    {
        $plain = 'TempPass1234';
        $user = User::query()->create([
            'name' => 'Sam Owner',
            'email' => 'sam.lead@example.com',
            'password' => $plain,
            'email_verified_at' => now(),
            'workspace_status' => User::WORKSPACE_PROVISIONAL,
            'signup_meta' => [],
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sam.lead@example.com',
            'password' => $plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace_incomplete', true);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/signup/complete-workspace', [
            'answers' => $this->validAnswers([
                'owner_email' => 'sam.lead@example.com',
                'owner_first_name' => 'Sam',
                'owner_last_name' => 'Owner',
            ]),
        ])
            ->assertCreated()
            ->assertJsonPath('data.workspace_incomplete', false)
            ->assertJsonPath('data.tenant.slug', 'bloom-hair');

        $user->refresh();
        $this->assertFalse($user->needsWorkspace());
        $this->assertDatabaseHas('tenants', [
            'slug' => 'bloom-hair',
            'status' => 'active',
        ]);
    }

    public function test_referral_share_url_points_at_marketing_home(): void
    {
        app(PlatformReferralSettingService::class)->update([
            'enabled' => true,
            'reward_type' => PlatformReferralSetting::REWARD_ACCOUNT_CREDIT,
            'reward_amount' => 5000,
            'qualification_goal' => PlatformReferralSetting::GOAL_TENANT_ACTIVATED,
        ]);

        $ctx = $this->seedTenantContext();
        config(['app.frontend_url' => 'https://app.neatmeet.test']);

        $payload = app(PlatformReferralProgramService::class)->tenantSharePayload($ctx['tenant']);

        $this->assertTrue($payload['enabled']);
        $this->assertNotNull($payload['code']);
        $this->assertStringStartsWith('https://app.neatmeet.test/?ref=', (string) $payload['share_url']);
        $this->assertStringNotContainsString('login?tab=signup', (string) $payload['share_url']);
    }

    public function test_lead_workspace_completion_rewards_referrer(): void
    {
        $ctx = $this->seedTenantContext();
        app(PlatformReferralSettingService::class)->update([
            'enabled' => true,
            'reward_type' => PlatformReferralSetting::REWARD_ACCOUNT_CREDIT,
            'reward_amount' => 7500,
            'qualification_goal' => PlatformReferralSetting::GOAL_TENANT_ACTIVATED,
        ]);

        $program = app(PlatformReferralProgramService::class);
        $invite = $program->ensureInviteForTenant($ctx['tenant']);

        $user = User::query()->create([
            'name' => 'Referred Owner',
            'email' => 'referred@example.com',
            'password' => 'TempPass9999',
            'email_verified_at' => now(),
            'workspace_status' => User::WORKSPACE_PROVISIONAL,
            'signup_meta' => ['referral_code' => $invite->code],
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/signup/complete-workspace', [
            'answers' => $this->validAnswers([
                'business_name' => 'Referred Salon',
                'slug' => 'referred-salon',
                'owner_email' => 'referred@example.com',
                'owner_first_name' => 'Referred',
                'owner_last_name' => 'Owner',
            ]),
        ])->assertCreated();

        $referredId = Tenant::query()->where('slug', 'referred-salon')->value('id');
        $this->assertNotNull($referredId);
        $this->assertDatabaseHas('platform_referral_conversions', [
            'referred_tenant_id' => $referredId,
            'status' => PlatformReferralConversion::STATUS_REWARDED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAnswers(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bloom Hair',
            'trading_name' => 'Bloom',
            'slug' => 'bloom-hair',
            'business_type' => 'boutique',
            'timezone' => 'Europe/London',
            'owner_first_name' => 'Alex',
            'owner_last_name' => 'Taylor',
            'owner_email' => 'owner@bloom.test',
            'owner_whatsapp' => '+447700900123',
            'contact_email' => 'hello@bloom.test',
            'location_name' => 'Main salon',
            'address_line1' => '1 High Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
            'desired_plan_slug' => 'basic',
            'services' => [
                [
                    'name' => 'Blow dry',
                    'category' => 'Hair',
                    'description' => 'Classic blow dry',
                    'image_url' => null,
                    'duration_minutes' => 45,
                    'base_price_cents' => 3500,
                ],
            ],
        ], $overrides);
    }
}
