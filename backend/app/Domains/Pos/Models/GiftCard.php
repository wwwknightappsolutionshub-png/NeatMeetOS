<?php

namespace App\Domains\Pos\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'code',
        'initial_balance_cents',
        'current_balance_cents',
        'status',
        'issued_checkout_id',
        'issued_to_client_id',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance_cents' => 'integer',
            'current_balance_cents' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function issuedCheckout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'issued_checkout_id');
    }

    public function issuedToClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'issued_to_client_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }
}
