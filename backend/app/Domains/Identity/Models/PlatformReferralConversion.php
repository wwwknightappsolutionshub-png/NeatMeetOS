<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformReferralConversion extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    protected $fillable = [
        'invite_id',
        'referrer_tenant_id',
        'referred_tenant_id',
        'qualification_goal',
        'status',
        'reward_amount',
        'reward_type',
        'qualified_at',
        'rewarded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reward_amount' => 'integer',
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(PlatformReferralInvite::class, 'invite_id');
    }

    public function referrerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referrer_tenant_id');
    }

    public function referredTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referred_tenant_id');
    }
}
