<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientWalletEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'entry_type',
        'direction',
        'amount_cents',
        'balance_effective_at',
        'expires_at',
        'source_type',
        'source_id',
        'checkout_id',
        'appointment_id',
        'reference_type',
        'reference_id',
        'restores_entry_id',
        'notes',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
