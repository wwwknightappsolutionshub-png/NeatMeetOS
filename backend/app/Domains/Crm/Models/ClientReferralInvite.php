<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientReferralInvite extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'referrer_client_id',
        'code',
        'status',
        'share_message_snapshot',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ClientReferralConversion::class, 'invite_id');
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(ClientReferralEmailSend::class, 'invite_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
