<?php

namespace App\Shared\Services;

use App\Shared\Models\IpBan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AbuseGuard
{
    public function isBanned(string $ip): bool
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === '') {
            return false;
        }

        $ban = IpBan::query()->where('ip', $ip)->first();
        if ($ban === null) {
            return false;
        }

        if (! $ban->isActive()) {
            $ban->delete();

            return false;
        }

        return true;
    }

    public function unban(string $ip): bool
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === '') {
            return false;
        }

        $deleted = IpBan::query()->where('ip', $ip)->delete();

        return $deleted > 0;
    }

    public function recordTurnstileFailure(string $ip): void
    {
        $cfg = config('security.abuse.turnstile_failures');
        $this->recordAndMaybeBan(
            $ip,
            'turnstile',
            (int) $cfg['max'],
            (int) $cfg['minutes'],
            (int) $cfg['ban_hours'],
            'Repeated Turnstile verification failures',
            IpBan::SOURCE_TURNSTILE,
        );
    }

    public function recordLoginFailure(string $ip): void
    {
        $cfg = config('security.abuse.login_failures');
        $this->recordAndMaybeBan(
            $ip,
            'login',
            (int) $cfg['max'],
            (int) $cfg['minutes'],
            (int) $cfg['ban_hours'],
            'Repeated failed login attempts',
            IpBan::SOURCE_LOGIN,
        );
    }

    public function recordThrottleHit(string $ip): void
    {
        $cfg = config('security.abuse.throttle_hits');
        $this->recordAndMaybeBan(
            $ip,
            'throttle',
            (int) $cfg['max'],
            (int) $cfg['minutes'],
            (int) $cfg['ban_hours'],
            'Excessive rate-limit hits',
            IpBan::SOURCE_THROTTLE,
        );
    }

    public function recordHoneypot(string $ip): void
    {
        $hours = (int) config('security.abuse.honeypot.ban_hours', 24);
        $this->ban($ip, 'Honeypot trap filled (likely bot)', IpBan::SOURCE_HONEYPOT, $hours);
    }

    public function ban(string $ip, string $reason, string $source, ?int $hours): void
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return;
        }

        $until = $hours === null ? null : now()->addHours($hours);

        $existing = IpBan::query()->where('ip', $ip)->first();
        if ($existing !== null) {
            $existing->update([
                'reason' => $reason,
                'source' => $source,
                'banned_until' => $until,
                'hit_count' => $existing->hit_count + 1,
            ]);
        } else {
            IpBan::query()->create([
                'ip' => $ip,
                'reason' => $reason,
                'source' => $source,
                'banned_until' => $until,
                'hit_count' => 1,
            ]);
        }

        Log::warning('security.ip_banned', [
            'ip' => $ip,
            'reason' => $reason,
            'source' => $source,
            'banned_until' => $until?->toIso8601String(),
        ]);
    }

    private function recordAndMaybeBan(
        string $ip,
        string $bucket,
        int $max,
        int $windowMinutes,
        int $banHours,
        string $reason,
        string $source,
    ): void {
        $ip = $this->normalizeIp($ip);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return;
        }

        $key = "abuse:{$bucket}:{$ip}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes($windowMinutes));

        if ($bucket === 'turnstile') {
            Log::warning('security.turnstile_failed', ['ip' => $ip, 'count' => $count]);
        }

        if ($count >= $max) {
            $this->ban($ip, $reason, $source, $banHours);
            Cache::forget($key);
        }
    }

    private function normalizeIp(string $ip): string
    {
        return trim($ip);
    }
}
