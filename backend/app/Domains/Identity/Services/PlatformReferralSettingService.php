<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformReferralSetting;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PlatformReferralSettingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getOrCreate(): PlatformReferralSetting
    {
        $existing = PlatformReferralSetting::query()->orderBy('created_at')->first();
        if ($existing !== null) {
            return $existing;
        }

        return PlatformReferralSetting::query()->create([
            'enabled' => false,
            'reward_type' => PlatformReferralSetting::REWARD_ACCOUNT_CREDIT,
            'reward_amount' => 5000,
            'qualification_goal' => PlatformReferralSetting::GOAL_TENANT_ACTIVATED,
            'qualification_days' => null,
            'share_headline' => 'Invite a salon to NeatMeet OS',
            'share_body' => 'Refer another business. When they activate, you earn the platform reward.',
        ]);
    }

    public function update(array $data): PlatformReferralSetting
    {
        $setting = $this->getOrCreate();

        return DB::transaction(function () use ($setting, $data) {
            $fields = array_intersect_key($data, array_flip([
                'enabled', 'reward_type', 'reward_amount', 'qualification_goal',
                'qualification_days', 'share_headline', 'share_body', 'metadata',
            ]));

            if (array_key_exists('enabled', $fields)) {
                $fields['enabled'] = filter_var($fields['enabled'], FILTER_VALIDATE_BOOLEAN);
            }

            $old = $setting->only(array_keys($fields));
            $setting->fill($fields);
            $setting->save();

            $this->auditLogger->log('platform_referral_settings.updated', $setting, $old, $setting->only(array_keys($fields)));

            return $setting->fresh();
        });
    }
}
