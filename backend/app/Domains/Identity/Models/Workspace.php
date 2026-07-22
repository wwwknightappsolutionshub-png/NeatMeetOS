<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workspace extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_CHAIR = 'chair';

    public const TYPE_ROOM = 'room';

    public const TYPE_STATION = 'station';

    public const TYPE_SEAT = 'seat';

    public const TYPE_SLOT = 'slot';

    protected $fillable = [
        'tenant_id',
        'location_id',
        'name',
        'code',
        'workspace_type',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class, 'team_member_workspace');
    }
}
