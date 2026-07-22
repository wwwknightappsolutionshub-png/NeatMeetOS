<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformUpgradeDiscountClaim;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformUpgradeDiscountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{claim: PlatformUpgradeDiscountClaim, plain_token: string}
     */
    public function issue(
        Tenant $tenant,
        User $owner,
        string $path,
        int $percent,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $plain = Str::random(48);
        $code = 'NM'.strtoupper(Str::random(8));

        $claim = PlatformUpgradeDiscountClaim::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'code' => $code,
            'token_hash' => hash('sha256', $plain),
            'path' => $path,
            'percent' => $percent,
            'status' => PlatformUpgradeDiscountClaim::STATUS_ISSUED,
            'expires_at' => $expiresAt ?? now()->addDays(14),
        ]);

        return ['claim' => $claim, 'plain_token' => $plain];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewByToken(string $plainToken): array
    {
        $claim = $this->findByToken($plainToken);
        $this->assertUsable($claim);
        $tenant = Tenant::query()->findOrFail($claim->tenant_id);

        $trialEnds = $tenant->subscription()->withoutGlobalScopes()->first()?->trial_ends_at;

        return [
            'code' => $claim->code,
            'percent' => $claim->percent,
            'path' => $claim->path,
            'status' => $claim->status,
            'expires_at' => $claim->expires_at?->toIso8601String(),
            'trial_ends_at' => $trialEnds?->toIso8601String(),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->trading_name ?: $tenant->name,
                'slug' => $tenant->slug,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function claimByToken(string $plainToken, User $user): array
    {
        $claim = $this->findByToken($plainToken);
        $this->assertUsable($claim);

        if ($claim->status === PlatformUpgradeDiscountClaim::STATUS_CLAIMED) {
            return [
                'code' => $claim->code,
                'percent' => $claim->percent,
                'path' => $claim->path,
                'status' => $claim->status,
                'claimed_at' => $claim->claimed_at?->toIso8601String(),
                'expires_at' => $claim->expires_at?->toIso8601String(),
                'message' => 'Discount already claimed. Share this code with billing when you upgrade: '.$claim->code,
            ];
        }

        if (! $this->userBelongsToTenant($user, $claim->tenant_id) && ! $user->is_platform_admin) {
            throw ValidationException::withMessages([
                'token' => ['This offer belongs to another workspace.'],
            ]);
        }

        $claim->forceFill([
            'status' => PlatformUpgradeDiscountClaim::STATUS_CLAIMED,
            'claimed_at' => now(),
            'user_id' => $user->id,
        ])->save();

        $this->audit->log('platform.upgrade_discount.claimed', $claim, null, [
            'code' => $claim->code,
            'percent' => $claim->percent,
            'tenant_id' => $claim->tenant_id,
        ], $user);

        return [
            'code' => $claim->code,
            'percent' => $claim->percent,
            'path' => $claim->path,
            'status' => $claim->status,
            'claimed_at' => $claim->claimed_at?->toIso8601String(),
            'expires_at' => $claim->expires_at?->toIso8601String(),
            'message' => 'Discount claimed. Share this code with billing when you upgrade: '.$claim->code,
        ];
    }

    private function findByToken(string $plainToken): PlatformUpgradeDiscountClaim
    {
        $claim = PlatformUpgradeDiscountClaim::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($claim === null) {
            throw ValidationException::withMessages(['token' => ['This upgrade offer is invalid.']]);
        }

        return $claim;
    }

    private function assertUsable(PlatformUpgradeDiscountClaim $claim): void
    {
        if ($claim->expires_at !== null && $claim->expires_at->isPast()) {
            $claim->forceFill(['status' => PlatformUpgradeDiscountClaim::STATUS_EXPIRED])->save();
            throw ValidationException::withMessages(['token' => ['This upgrade offer has expired.']]);
        }

        if (in_array($claim->status, [
            PlatformUpgradeDiscountClaim::STATUS_REDEEMED,
            PlatformUpgradeDiscountClaim::STATUS_EXPIRED,
        ], true)) {
            throw ValidationException::withMessages(['token' => ['This upgrade offer is no longer available.']]);
        }
    }

    private function userBelongsToTenant(User $user, string $tenantId): bool
    {
        return $user->teamMembers()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }
}
