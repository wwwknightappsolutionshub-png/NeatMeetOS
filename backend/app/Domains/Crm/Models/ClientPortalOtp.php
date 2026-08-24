<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalOtp extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'email',
        'phone_normalized',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
