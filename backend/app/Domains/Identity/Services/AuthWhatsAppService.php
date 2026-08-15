<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Shared\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Platform auth WhatsApp (password reset, etc.) — uses Genius via PlatformWhatsAppSettingsService.
 */
class AuthWhatsAppService
{
    public function __construct(
        private readonly PlatformWhatsAppSettingsService $whatsapp,
        private readonly AuthMailService $mail,
    ) {}

    /**
     * @return array{sent: bool, skipped?: bool, reason?: string, phone?: string}
     */
    public function sendPasswordReset(User $user, string $plainToken, ?string $tenantId = null): array
    {
        $phone = $this->resolvePhone($user, $tenantId);
        if ($phone === null) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'missing_phone'];
        }

        $url = $this->mail->frontendResetUrl($plainToken);
        $name = trim((string) $user->name) ?: 'there';
        $message = <<<TXT
*Reset your NeatMeet OS password*

Hi {$name},

We received a request to reset your password. Open the link below to choose a new one. This link expires in 60 minutes.

{$url}

If you did not request a reset, you can ignore this message.
TXT;

        try {
            $result = $this->whatsapp->sendOperational($phone, $message, [
                'tenant_id' => $tenantId,
                'purpose' => 'auth.password_reset',
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Password reset WhatsApp failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'skipped' => true, 'reason' => 'send_failed', 'phone' => $phone];
        }

        if (! ($result['ok'] ?? false)) {
            Log::info('Password reset WhatsApp not sent', [
                'user_id' => $user->id,
                'error' => $result['error'] ?? null,
                'source' => $result['source'] ?? null,
            ]);

            return [
                'sent' => false,
                'skipped' => true,
                'reason' => (string) ($result['error'] ?? 'not_ready'),
                'phone' => $phone,
            ];
        }

        return ['sent' => true, 'phone' => $phone];
    }

    public function resolvePhone(User $user, ?string $tenantId = null): ?string
    {
        $candidates = [];

        $meta = is_array($user->signup_meta) ? $user->signup_meta : [];
        foreach (['whatsapp', 'phone', 'owner_whatsapp'] as $key) {
            if (! empty($meta[$key])) {
                $candidates[] = (string) $meta[$key];
            }
        }

        $memberQuery = TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->orderByDesc('created_at');
        if ($tenantId !== null) {
            $memberQuery->where('tenant_id', $tenantId);
        }
        $member = $memberQuery->first();
        if ($member && filled($member->phone)) {
            $candidates[] = (string) $member->phone;
        }

        $tenant = null;
        if ($tenantId !== null) {
            $tenant = Tenant::query()->find($tenantId);
        } else {
            $tenant = $user->resolveActiveTeamMember()?->tenant;
        }
        if ($tenant && filled($tenant->owner_whatsapp)) {
            $candidates[] = (string) $tenant->owner_whatsapp;
        }
        if ($tenant && filled($tenant->contact_phone)) {
            $candidates[] = (string) $tenant->contact_phone;
        }

        foreach ($candidates as $raw) {
            $normalized = PhoneNormalizer::normalize($raw);
            if (PhoneNormalizer::isValid($normalized)) {
                return $normalized;
            }
        }

        return null;
    }
}
