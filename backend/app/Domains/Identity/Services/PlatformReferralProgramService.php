<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformReferralConversion;
use App\Domains\Identity\Models\PlatformReferralInvite;
use App\Domains\Identity\Models\PlatformReferralSetting;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantSubscription;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformReferralProgramService
{
    public function __construct(
        private readonly PlatformReferralSettingService $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function ensureInviteForTenant(Tenant $tenant): PlatformReferralInvite
    {
        $existing = PlatformReferralInvite::query()
            ->where('referrer_tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return PlatformReferralInvite::query()->create([
            'referrer_tenant_id' => $tenant->id,
            'code' => $this->uniqueCode($tenant),
            'status' => 'active',
            'conversions_count' => 0,
        ]);
    }

    /**
     * Attach a pending conversion when a referred salon signs up with a code.
     */
    public function attachOnSignup(Tenant $referred, ?string $code): ?PlatformReferralConversion
    {
        $settings = $this->settings->getOrCreate();
        if (! $settings->enabled) {
            return null;
        }

        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $invite = PlatformReferralInvite::query()
            ->where('code', $code)
            ->where('status', 'active')
            ->first();

        if ($invite === null || $invite->referrer_tenant_id === $referred->id) {
            return null;
        }

        return DB::transaction(function () use ($invite, $referred, $settings) {
            $conversion = PlatformReferralConversion::query()->firstOrCreate(
                ['referred_tenant_id' => $referred->id],
                [
                    'invite_id' => $invite->id,
                    'referrer_tenant_id' => $invite->referrer_tenant_id,
                    'qualification_goal' => $settings->qualification_goal,
                    'status' => PlatformReferralConversion::STATUS_PENDING,
                    'reward_amount' => $settings->reward_amount,
                    'reward_type' => $settings->reward_type,
                    'metadata' => ['attached_at' => now()->toIso8601String()],
                ],
            );

            $settingsBag = $referred->settings ?? [];
            $settingsBag['signup'] = array_merge($settingsBag['signup'] ?? [], [
                'platform_referral_code' => $invite->code,
                'platform_referral_invite_id' => $invite->id,
            ]);
            $referred->forceFill(['settings' => $settingsBag])->save();

            $this->auditLogger->log('platform_referral.attached', $referred, null, [
                'invite_id' => $invite->id,
                'referrer_tenant_id' => $invite->referrer_tenant_id,
            ]);

            return $conversion;
        });
    }

    /**
     * Qualify + reward when a referred salon activates (if goal is referred_tenant_activated).
     */
    public function handleTenantActivated(Tenant $referred): ?PlatformReferralConversion
    {
        $conversion = PlatformReferralConversion::query()
            ->where('referred_tenant_id', $referred->id)
            ->where('status', PlatformReferralConversion::STATUS_PENDING)
            ->first();

        if ($conversion === null) {
            return null;
        }

        if ($conversion->qualification_goal !== PlatformReferralSetting::GOAL_TENANT_ACTIVATED) {
            return $conversion;
        }

        return $this->qualifyAndReward($conversion);
    }

    public function qualifyAndReward(PlatformReferralConversion $conversion): PlatformReferralConversion
    {
        if ($conversion->status === PlatformReferralConversion::STATUS_REWARDED) {
            return $conversion;
        }

        return DB::transaction(function () use ($conversion) {
            $conversion->forceFill([
                'status' => PlatformReferralConversion::STATUS_QUALIFIED,
                'qualified_at' => now(),
            ])->save();

            $referrer = Tenant::query()->find($conversion->referrer_tenant_id);
            if ($referrer !== null) {
                $this->applyReward($referrer, $conversion);
            }

            $conversion->forceFill([
                'status' => PlatformReferralConversion::STATUS_REWARDED,
                'rewarded_at' => now(),
            ])->save();

            PlatformReferralInvite::query()
                ->where('id', $conversion->invite_id)
                ->increment('conversions_count');

            $this->auditLogger->log('platform_referral.rewarded', $referrer, null, [
                'conversion_id' => $conversion->id,
                'reward_type' => $conversion->reward_type,
                'reward_amount' => $conversion->reward_amount,
            ]);

            return $conversion->fresh();
        });
    }

    private function applyReward(Tenant $referrer, PlatformReferralConversion $conversion): void
    {
        $amount = (int) ($conversion->reward_amount ?? 0);
        if ($amount <= 0) {
            return;
        }

        if ($conversion->reward_type === PlatformReferralSetting::REWARD_SUBSCRIPTION_DAYS) {
            $subscription = TenantSubscription::withoutGlobalScopes()
                ->where('tenant_id', $referrer->id)
                ->orderByDesc('created_at')
                ->first();
            if ($subscription === null) {
                return;
            }
            $end = $subscription->current_period_end?->copy() ?? now();
            if ($end->lt(now())) {
                $end = now();
            }
            $subscription->forceFill([
                'current_period_end' => $end->addDays($amount),
            ])->save();

            return;
        }

        $settings = $referrer->settings ?? [];
        $credit = (int) ($settings['platform_referral_credit_cents'] ?? 0);
        $settings['platform_referral_credit_cents'] = $credit + $amount;
        $referrer->forceFill(['settings' => $settings])->save();
    }

    /**
     * @return array{enabled: bool, code: string|null, share_url: string|null, headline: string|null, body: string|null, reward_type: string, reward_amount: int, qualification_goal: string, conversions_count: int}
     */
    public function tenantSharePayload(Tenant $tenant): array
    {
        $settings = $this->settings->getOrCreate();
        if (! $settings->enabled) {
            return [
                'enabled' => false,
                'code' => null,
                'share_url' => null,
                'headline' => $settings->share_headline,
                'body' => $settings->share_body,
                'reward_type' => $settings->reward_type,
                'reward_amount' => (int) $settings->reward_amount,
                'qualification_goal' => $settings->qualification_goal,
                'conversions_count' => 0,
            ];
        }

        $invite = $this->ensureInviteForTenant($tenant);
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return [
            'enabled' => true,
            'code' => $invite->code,
            'share_url' => $base.'/?ref='.urlencode($invite->code),
            'headline' => $settings->share_headline,
            'body' => $settings->share_body,
            'reward_type' => $settings->reward_type,
            'reward_amount' => (int) $settings->reward_amount,
            'qualification_goal' => $settings->qualification_goal,
            'conversions_count' => (int) $invite->conversions_count,
        ];
    }

    private function uniqueCode(Tenant $tenant): string
    {
        $base = strtoupper(Str::substr(Str::slug($tenant->slug, ''), 0, 6));
        if ($base === '') {
            $base = 'NM';
        }

        do {
            $code = $base.Str::upper(Str::random(4));
        } while (PlatformReferralInvite::query()->where('code', $code)->exists());

        return $code;
    }
}
