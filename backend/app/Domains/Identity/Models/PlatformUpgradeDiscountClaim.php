<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformUpgradeDiscountClaim extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'code',
        'token_hash',
        'path',
        'percent',
        'status',
        'expires_at',
        'claimed_at',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
