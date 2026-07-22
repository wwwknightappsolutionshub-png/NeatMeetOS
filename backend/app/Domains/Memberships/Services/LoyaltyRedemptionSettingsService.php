<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Models\MembershipLoyaltySetting;
use App\Shared\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class LoyaltyRedemptionSettingsService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function get(): MembershipLoyaltySetting
    {
        return MembershipLoyaltySetting::query()->firstOrCreate(
            ['tenant_id' => $this->scope->tenantId()],
            [
                'is_loyalty_redemption_enabled' => false,
                'points_per_redemption_block' => 100,
                'value_cents_per_block' => 1000,
                'crm_join_signup_points' => MembershipLoyaltySetting::DEFAULT_CRM_JOIN_SIGNUP_POINTS,
            ],
        );
    }

    public function update(array $data): MembershipLoyaltySetting
    {
        $setting = $this->get();
        $before = $setting->only([
            'is_loyalty_redemption_enabled',
            'points_per_redemption_block',
            'value_cents_per_block',
            'crm_join_signup_points',
        ]);

        $setting->fill([
            'is_loyalty_redemption_enabled' => $data['is_loyalty_redemption_enabled'] ?? $setting->is_loyalty_redemption_enabled,
            'points_per_redemption_block' => $data['points_per_redemption_block'] ?? $setting->points_per_redemption_block,
            'value_cents_per_block' => $data['value_cents_per_block'] ?? $setting->value_cents_per_block,
            'crm_join_signup_points' => array_key_exists('crm_join_signup_points', $data)
                ? (int) $data['crm_join_signup_points']
                : $setting->crm_join_signup_points,
        ]);
        $setting->save();

        $this->auditLogger->log('loyalty_redemption_settings.updated', $setting, $before, $setting->only([
            'is_loyalty_redemption_enabled',
            'points_per_redemption_block',
            'value_cents_per_block',
            'crm_join_signup_points',
        ]));

        return $setting;
    }

    /**
     * @return array{points: int, value_cents: int, blocks: int}
     */
    public function computeRedemptionValue(int $points): array
    {
        $setting = $this->get();
        $this->assertEnabled($setting);
        $this->assertValidPoints($points, $setting);

        $blocks = intdiv($points, $setting->points_per_redemption_block);
        $valueCents = $blocks * $setting->value_cents_per_block;

        return [
            'points' => $blocks * $setting->points_per_redemption_block,
            'value_cents' => $valueCents,
            'blocks' => $blocks,
        ];
    }

    public function maxRedeemablePoints(int $clientBalance): int
    {
        $setting = $this->get();
        if (! $setting->is_loyalty_redemption_enabled || $setting->points_per_redemption_block <= 0) {
            return 0;
        }

        return intdiv($clientBalance, $setting->points_per_redemption_block) * $setting->points_per_redemption_block;
    }

    public function redeemableValueCents(int $clientBalance): int
    {
        $points = $this->maxRedeemablePoints($clientBalance);
        if ($points <= 0) {
            return 0;
        }

        return $this->computeRedemptionValue($points)['value_cents'];
    }

    private function assertEnabled(MembershipLoyaltySetting $setting): void
    {
        if (! $setting->is_loyalty_redemption_enabled) {
            throw ValidationException::withMessages([
                'loyalty' => ['Loyalty redemption is not enabled.'],
            ]);
        }
    }

    private function assertValidPoints(int $points, MembershipLoyaltySetting $setting): void
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => ['Points must be greater than zero.']]);
        }

        if ($setting->points_per_redemption_block <= 0) {
            throw ValidationException::withMessages(['points' => ['Invalid redemption block size.']]);
        }

        if ($points % $setting->points_per_redemption_block !== 0) {
            throw ValidationException::withMessages([
                'points' => ['Points must be a multiple of '.$setting->points_per_redemption_block.'.'],
            ]);
        }
    }
}
