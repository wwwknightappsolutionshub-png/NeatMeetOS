<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformReferralInvite extends Model
{
    use HasUuid;

    protected $fillable = [
        'referrer_tenant_id',
        'code',
        'status',
        'conversions_count',
    ];

    protected function casts(): array
    {
        return [
            'conversions_count' => 'integer',
        ];
    }

    public function referrerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referrer_tenant_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(PlatformReferralConversion::class, 'invite_id');
    }
}
