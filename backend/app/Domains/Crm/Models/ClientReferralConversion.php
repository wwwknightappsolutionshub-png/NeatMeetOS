<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReferralConversion extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'invite_id',
        'referrer_client_id',
        'referred_client_id',
        'referrer_points_awarded',
        'referred_bonus_pending',
        'referrer_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'referrer_points_awarded' => 'integer',
            'referred_bonus_pending' => 'boolean',
            'referrer_notified_at' => 'datetime',
        ];
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(ClientReferralInvite::class, 'invite_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_client_id');
    }
}
