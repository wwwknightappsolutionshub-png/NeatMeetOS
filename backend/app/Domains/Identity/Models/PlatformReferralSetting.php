<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformReferralSetting extends Model
{
    use HasUuid;

    public const REWARD_ACCOUNT_CREDIT = 'account_credit_cents';

    public const REWARD_SUBSCRIPTION_DAYS = 'subscription_days';

    public const GOAL_TENANT_ACTIVATED = 'referred_tenant_activated';

    public const GOAL_FIRST_PAID_PERIOD = 'referred_tenant_first_paid_period';

    protected $fillable = [
        'enabled',
        'reward_type',
        'reward_amount',
        'qualification_goal',
        'qualification_days',
        'share_headline',
        'share_body',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'reward_amount' => 'integer',
            'qualification_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public static function rewardTypes(): array
    {
        return [self::REWARD_ACCOUNT_CREDIT, self::REWARD_SUBSCRIPTION_DAYS];
    }

    public static function qualificationGoals(): array
    {
        return [self::GOAL_TENANT_ACTIVATED, self::GOAL_FIRST_PAID_PERIOD];
    }
}
