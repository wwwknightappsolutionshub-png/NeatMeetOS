<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReferralEmailSend extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SIMULATED = 'simulated';

    protected $fillable = [
        'tenant_id',
        'referrer_client_id',
        'invite_id',
        'recipient_email',
        'status',
        'provider_ref',
        'error',
    ];

    public function invite(): BelongsTo
    {
        return $this->belongsTo(ClientReferralInvite::class, 'invite_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }
}
