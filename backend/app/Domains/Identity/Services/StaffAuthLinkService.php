<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\AuthActionToken;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthLinkService
{
    public function __construct(
        private readonly AuthActionTokenService $tokens,
        private readonly AuthMailService $mail,
        private readonly AuditLogger $audit,
    ) {}

    public function requestMagicLogin(string $email): void
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();
        if ($user === null) {
            // Do not reveal whether the email exists.
            return;
        }

        if ($this->tenantBlocksLogin($user)) {
            return;
        }

        $tenantId = $user->currentTeamMember?->tenant_id;
        $plain = $this->tokens->issue($user, AuthActionToken::PURPOSE_MAGIC_LOGIN, $tenantId, 15);
        $this->mail->sendMagicLogin($user, $plain);
    }

    /**
     * @return array{token: string, user: User, tenant: ?Tenant}
     */
    public function consumeMagicLogin(string $plainToken, ?string $deviceName = null): array
    {
        $action = $this->tokens->consume($plainToken, AuthActionToken::PURPOSE_MAGIC_LOGIN);
        $user = User::query()->with('currentTeamMember.tenant')->findOrFail($action->user_id);

        if ($this->tenantBlocksLogin($user)) {
            throw ValidationException::withMessages([
                'token' => ['This account is not active yet. Check your email for the activation link.'],
            ]);
        }

        $sanctum = $user->createToken($deviceName ?? 'neatmeet-os-web')->plainTextToken;
        $this->audit->log('auth.magic_login', $user, null, ['user_id' => $user->id], $user);

        return [
            'token' => $sanctum,
            'user' => $user,
            'tenant' => $user->currentTeamMember?->tenant,
        ];
    }

    public function requestPasswordReset(string $email): void
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();
        if ($user === null) {
            return;
        }

        $tenantId = $user->currentTeamMember?->tenant_id;
        $plain = $this->tokens->issue($user, AuthActionToken::PURPOSE_PASSWORD_RESET, $tenantId, 60);
        $this->mail->sendPasswordReset($user, $plain);
    }

    public function resetPassword(string $plainToken, string $password): void
    {
        $action = $this->tokens->consume($plainToken, AuthActionToken::PURPOSE_PASSWORD_RESET);
        $user = User::query()->findOrFail($action->user_id);
        $user->forceFill(['password' => Hash::make($password)])->save();
        $this->audit->log('auth.password_reset', $user, null, ['user_id' => $user->id], $user);
    }

    private function tenantBlocksLogin(User $user): bool
    {
        if ($user->is_platform_admin) {
            return false;
        }

        $user->loadMissing('currentTeamMember.tenant');
        $tenant = $user->currentTeamMember?->tenant;
        if ($tenant === null) {
            return false;
        }

        return $tenant->status === 'pending_activation';
    }
}
