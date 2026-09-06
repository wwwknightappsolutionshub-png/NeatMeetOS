<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\TenantSessionService;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Jobs\SendTenantSessionLogoutWarningJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\TestCase;

class TenantSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('security.turnstile.enabled', false);
        Cache::flush();
    }

    /**
     * @return array{tenant: Tenant, user: User}
     */
    private function seedTenantOwner(?string $whatsapp = '+447700900999'): array
    {
        $planId = (string) Str::uuid();
        \DB::table('subscription_plans')->insert([
            'id' => $planId,
            'name' => 'Starter',
            'slug' => 'starter-'.Str::random(6),
            'features' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Session Salon',
            'slug' => 'session-salon-'.Str::random(5),
            'status' => 'active',
            'subscription_plan_id' => $planId,
            'owner_whatsapp' => $whatsapp,
        ]);

        $user = User::factory()->create([
            'email' => 'owner-session-'.Str::random(5).'@test.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => false,
        ]);

        TeamMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'employment_type' => TeamMember::EMPLOYMENT_OWNER,
            'display_name' => 'Owner',
            'phone' => $whatsapp,
            'is_active' => true,
        ]);

        return compact('tenant', 'user');
    }

    public function test_tenant_login_issues_four_hour_token_and_schedules_warning(): void
    {
        Queue::fake();
        ['user' => $user, 'tenant' => $tenant] = $this->seedTenantOwner();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $expiresAt = $response->json('data.expires_at');
        $this->assertNotNull($expiresAt);
        $parsed = \Carbon\Carbon::parse($expiresAt);
        $this->assertTrue(
            $parsed->between(
                now()->addHours(4)->subMinute(),
                now()->addHours(4)->addMinute(),
            )
        );

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);

        Queue::assertPushed(SendTenantSessionLogoutWarningJob::class, function (SendTenantSessionLogoutWarningJob $job) use ($token) {
            return (string) $job->tokenId === (string) $token->id
                && $job->expectedExpiresAt === (int) $token->expires_at->getTimestamp();
        });
    }

    public function test_platform_admin_login_has_no_session_expiry(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'platform-session@test.local',
            'password' => Hash::make('password'),
            'is_platform_admin' => true,
            'platform_role' => 'owner',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.expires_at', null);

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->first();
        $this->assertNotNull($token);
        $this->assertNull($token->expires_at);
        Queue::assertNotPushed(SendTenantSessionLogoutWarningJob::class);
    }

    public function test_admin_activity_extends_session_expiry(): void
    {
        Queue::fake();
        ['user' => $user, 'tenant' => $tenant] = $this->seedTenantOwner();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $plain = $login->json('data.token');
        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();
        $originalExpiry = $token->expires_at->copy();

        $this->travel(30)->minutes();
        Cache::flush();

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $token->refresh();
        $this->assertTrue($token->expires_at->greaterThan($originalExpiry));
        $this->assertTrue(
            $token->expires_at->between(
                now()->addHours(4)->subMinute(),
                now()->addHours(4)->addMinute(),
            )
        );
    }

    public function test_logout_warning_skips_when_session_was_extended(): void
    {
        ['user' => $user] = $this->seedTenantOwner();
        $issued = app(TenantSessionService::class)->issueToken($user);
        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();
        $staleExpiry = (int) $token->expires_at->getTimestamp();

        $token->forceFill(['expires_at' => now()->addHours(5)])->save();

        $whatsApp = Mockery::mock(PlatformWhatsAppSettingsService::class);
        $whatsApp->shouldNotReceive('sendOperational');
        $this->app->instance(PlatformWhatsAppSettingsService::class, $whatsApp);

        $sent = app(TenantSessionService::class)->sendLogoutWarning($token->fresh(), $staleExpiry);
        $this->assertFalse($sent);
        $this->assertNotEmpty($issued['plain_text_token']);
    }

    public function test_logout_warning_sends_whatsapp_when_expiry_matches(): void
    {
        ['user' => $user] = $this->seedTenantOwner('+447700900888');
        app(TenantSessionService::class)->issueToken($user);
        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();
        $expected = (int) $token->expires_at->getTimestamp();

        $whatsApp = Mockery::mock(PlatformWhatsAppSettingsService::class);
        $whatsApp->shouldReceive('sendOperational')
            ->once()
            ->withArgs(function (string $to, string $message) {
                return $to === '+447700900888'
                    && str_contains($message, 'NeatMeet');
            })
            ->andReturn(['ok' => true, 'provider' => 'genius']);
        $this->app->instance(PlatformWhatsAppSettingsService::class, $whatsApp);

        $sent = app(TenantSessionService::class)->sendLogoutWarning($token, $expected);
        $this->assertTrue($sent);
    }
}
