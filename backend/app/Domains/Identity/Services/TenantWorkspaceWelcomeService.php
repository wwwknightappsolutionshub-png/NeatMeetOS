<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService;
use Illuminate\Support\Facades\Log;

/**
 * Catch-up workspace welcome for tenants who missed automated signup welcomes.
 */
class TenantWorkspaceWelcomeService
{
    public function __construct(
        private readonly AuthMailService $mail,
        private readonly PlatformSignupWhatsAppWelcomeService $whatsapp,
    ) {}

    /**
     * @return array{email_sent: bool, whatsapp: array<string, mixed>}
     */
    public function sendToEmail(string $email, ?string $phone = null): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$email}");
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user === null) {
            throw new \RuntimeException("No user found for {$email}");
        }

        $tenant = $this->resolveTenant($user);
        if ($tenant === null) {
            throw new \RuntimeException("No active tenant found for {$email}");
        }

        $normalizedPhone = $this->normalizePhone($phone ?? $tenant->owner_whatsapp ?? $this->phoneFromUser($user));

        $this->mail->sendWorkspaceWelcome($user, $tenant);

        $whatsappResult = ['sent' => false, 'skipped' => true, 'reason' => 'missing_phone'];
        if ($normalizedPhone !== null) {
            $whatsappResult = $this->whatsapp->sendWorkspaceWelcome($user, $tenant, $normalizedPhone);
        }

        Log::info('tenant.workspace_welcome.sent', [
            'email' => $email,
            'tenant_id' => $tenant->id,
            'whatsapp_sent' => (bool) ($whatsappResult['sent'] ?? false),
        ]);

        return [
            'email_sent' => true,
            'whatsapp' => $whatsappResult,
        ];
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '00')) {
            return '+'.substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            return '+44'.substr($phone, 1);
        }

        return '+'.$phone;
    }

    private function resolveTenant(User $user): ?Tenant
    {
        $teamMember = $user->resolveActiveTeamMember();
        if ($teamMember === null) {
            return null;
        }

        return Tenant::withoutGlobalScopes()->find($teamMember->tenant_id);
    }

    private function phoneFromUser(User $user): ?string
    {
        $meta = is_array($user->signup_meta) ? $user->signup_meta : [];
        $phone = preg_replace('/\s+/', '', trim((string) ($meta['whatsapp'] ?? $meta['phone'] ?? ''))) ?? '';

        return $phone !== '' ? $phone : null;
    }
}
