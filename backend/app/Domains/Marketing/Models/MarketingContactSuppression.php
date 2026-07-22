<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingContactSuppression extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'channel',
        'contact_value',
        'reason',
        'source',
        'is_active',
        'lifted_at',
        'notes',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lifted_at' => 'datetime',
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
}
