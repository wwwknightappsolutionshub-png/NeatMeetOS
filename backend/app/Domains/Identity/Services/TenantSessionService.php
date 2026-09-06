<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use App\Jobs\SendTenantSessionLogoutWarningJob;
use App\Shared\Support\PhoneNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues sliding 4-hour Sanctum sessions for tenant admin users and schedules
 * a WhatsApp logout warning 10 minutes before expiry.
 */
class TenantSessionService
{
    public function ttlSeconds(): int
    {
        return max(300, (int) config('tenant_session.ttl_seconds', 4 * 60 * 60));
    }

    public function warningBeforeSeconds(): int
    {
        $ttl = $this->ttlSeconds();
        $before = max(60, (int) config('tenant_session.warning_before_seconds', 10 * 60));

        return min($before, max(60, $ttl - 60));
    }

    public function shouldUseTimedSession(User $user): bool
    {
        if ($user->is_platform_admin) {
            return false;
        }

        return TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array{plain_text_token: string, expires_at: ?CarbonInterface}
     */
    public function issueToken(User $user, ?string $deviceName = null): array
    {
        $name = $deviceName ?: (string) config('tenant_session.token_name', 'neatmeet-os-web');

        if (! $this->shouldUseTimedSession($user)) {
            $created = $user->createToken($name);

            return [
                'plain_text_token' => $created->plainTextToken,
                'expires_at' => null,
            ];
        }

        $expiresAt = now()->addSeconds($this->ttlSeconds());
        $created = $user->createToken($name, ['*'], $expiresAt);
        $this->scheduleWarning($created->accessToken, $expiresAt);

        return [
            'plain_text_token' => $created->plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function touch(?PersonalAccessToken $token, ?User $user = null): void
    {
        if ($token === null || $token->expires_at === null) {
            return;
        }

        if ($token->name === 'platform-impersonation') {
            return;
        }

        $user ??= User::query()->find($token->tokenable_id);
        if ($user === null || ! $this->shouldUseTimedSession($user)) {
            return;
        }

        $throttle = max(15, (int) config('tenant_session.extend_throttle_seconds', 60));
        $cacheKey = 'tenant-session-extend:'.$token->id;
        if (! Cache::add($cacheKey, 1, $throttle)) {
            return;
        }

        $expiresAt = now()->addSeconds($this->ttlSeconds());
        $token->forceFill(['expires_at' => $expiresAt])->save();
        $this->scheduleWarning($token, $expiresAt);
    }

    public function scheduleWarning(PersonalAccessToken $token, CarbonInterface $expiresAt): void
    {
        $delayUntil = $expiresAt->copy()->subSeconds($this->warningBeforeSeconds());
        if ($delayUntil->lessThanOrEqualTo(now())) {
            return;
        }

        SendTenantSessionLogoutWarningJob::dispatch(
            (string) $token->id,
            (int) $expiresAt->getTimestamp(),
        )->delay($delayUntil);
    }

    public function resolveWhatsAppPhone(User $user): ?string
    {
        $member = TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('tenant')
            ->orderByDesc('id')
            ->first();

        $candidates = [
            $member?->phone,
            $member?->tenant?->owner_whatsapp,
            $member?->tenant?->contact_phone,
        ];

        $meta = is_array($user->signup_meta ?? null) ? $user->signup_meta : [];
        $candidates[] = $meta['whatsapp'] ?? null;
        $candidates[] = $meta['phone'] ?? null;
        $candidates[] = $meta['owner_whatsapp'] ?? null;

        foreach ($candidates as $raw) {
            $normalized = PhoneNormalizer::normalize(is_string($raw) ? $raw : null);
            if ($normalized !== '' && PhoneNormalizer::isValid($normalized)) {
                return str_starts_with($normalized, '+') ? $normalized : '+'.$normalized;
            }
        }

        return null;
    }

    public function sendLogoutWarning(PersonalAccessToken $token, int $expectedExpiresAt): bool
    {
        if ($token->expires_at === null) {
            return false;
        }

        if ((int) $token->expires_at->getTimestamp() !== $expectedExpiresAt) {
            return false;
        }

        if ($token->expires_at->lessThanOrEqualTo(now())) {
            return false;
        }

        $user = User::query()->find($token->tokenable_id);
        if ($user === null) {
            return false;
        }

        $phone = $this->resolveWhatsAppPhone($user);
        if ($phone === null) {
            Log::info('tenant_session.warning_skipped_no_phone', [
                'token_id' => $token->id,
                'user_id' => $user->id,
            ]);

            return false;
        }

        $message = (string) config(
            'tenant_session.warning_message',
            "You're About To Be Logged Out From Your NeatMeet, Visit Now To Stay Logged In",
        );

        $tenantId = TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->value('tenant_id');

        try {
            $result = app(\App\Domains\Notifications\Services\PlatformWhatsAppSettingsService::class)
                ->sendOperational($phone, $message, [
                    'tenant_id' => $tenantId,
                    'purpose' => 'tenant_session_logout_warning',
                    'token_id' => $token->id,
                ]);

            return ! empty($result['ok']);
        } catch (\Throwable $e) {
            Log::warning('tenant_session.warning_failed', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
