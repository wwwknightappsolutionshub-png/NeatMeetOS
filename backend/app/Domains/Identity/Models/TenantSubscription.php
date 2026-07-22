<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_SUSPENDED = 'suspended';

    public static function statuses(): array
    {
        return [
            self::STATUS_TRIAL,
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
            self::STATUS_CANCELED,
            self::STATUS_SUSPENDED,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'desired_plan_slug',
        'tier_unlocked',
        'tier_unlocked_at',
        'status',
        'billing_interval',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'provider',
        'external_subscription_id',
        'billing_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'tier_unlocked' => 'boolean',
            'tier_unlocked_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
