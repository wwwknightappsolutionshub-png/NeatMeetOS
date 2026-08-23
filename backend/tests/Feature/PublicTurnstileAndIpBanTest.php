<?php

namespace Tests\Feature;

use App\Shared\Models\IpBan;
use App\Shared\Services\AbuseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicTurnstileAndIpBanTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_when_turnstile_disabled(): void
    {
        Config::set('security.turnstile.enabled', false);
        Config::set('security.turnstile.secret_key', '');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
    }

    public function test_login_rejects_missing_turnstile_token_when_enabled(): void
    {
        Config::set('security.turnstile.enabled', true);
        Config::set('security.turnstile.secret_key', 'test-secret');

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'turnstile_failed');
    }

    public function test_login_accepts_valid_turnstile_token_when_enabled(): void
    {
        Config::set('security.turnstile.enabled', true);
        Config::set('security.turnstile.secret_key', 'test-secret');

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WrongPass1!',
            'turnstile_token' => 'valid-token',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'siteverify')
                && $request['response'] === 'valid-token'
                && $request['secret'] === 'test-secret';
        });
    }

    public function test_banned_ip_is_blocked_before_auth(): void
    {
        Config::set('security.turnstile.enabled', false);

        IpBan::query()->create([
            'ip' => '127.0.0.1',
            'reason' => 'test ban',
            'source' => IpBan::SOURCE_TURNSTILE,
            'banned_until' => now()->addDay(),
            'hit_count' => 1,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WrongPass1!',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'ip_banned');
    }

    public function test_abuse_guard_bans_after_turnstile_threshold(): void
    {
        Config::set('security.abuse.turnstile_failures.max', 3);
        Config::set('security.abuse.turnstile_failures.minutes', 15);
        Config::set('security.abuse.turnstile_failures.ban_hours', 24);

        /** @var AbuseGuard $abuse */
        $abuse = app(AbuseGuard::class);
        $ip = '203.0.113.50';

        $abuse->recordTurnstileFailure($ip);
        $abuse->recordTurnstileFailure($ip);
        $this->assertFalse($abuse->isBanned($ip));

        $abuse->recordTurnstileFailure($ip);
        $this->assertTrue($abuse->isBanned($ip));

        $this->assertTrue($abuse->unban($ip));
        $this->assertFalse($abuse->isBanned($ip));
    }

    public function test_expired_ban_is_cleared(): void
    {
        IpBan::query()->create([
            'ip' => '203.0.113.99',
            'reason' => 'expired',
            'source' => IpBan::SOURCE_LOGIN,
            'banned_until' => now()->subHour(),
            'hit_count' => 1,
        ]);

        /** @var AbuseGuard $abuse */
        $abuse = app(AbuseGuard::class);
        $this->assertFalse($abuse->isBanned('203.0.113.99'));
        $this->assertDatabaseMissing('ip_bans', ['ip' => '203.0.113.99']);
    }
}
