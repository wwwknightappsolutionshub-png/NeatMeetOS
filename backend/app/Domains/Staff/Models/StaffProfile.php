<?php

namespace App\Domains\Staff\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'team_member_id',
        'is_bookable',
        'show_in_online_booking',
        'accepts_walk_ins',
        'booking_display_name',
        'internal_notes',
        'default_workspace_id',
        'min_lead_time_minutes',
        'buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_bookable' => 'boolean',
            'show_in_online_booking' => 'boolean',
            'accepts_walk_ins' => 'boolean',
            'min_lead_time_minutes' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function defaultWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'default_workspace_id');
    }
}
