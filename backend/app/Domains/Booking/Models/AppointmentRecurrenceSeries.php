<?php

namespace App\Domains\Booking\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentRecurrenceSeries extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const PATTERN_WEEKLY = 'weekly';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'pattern',
        'interval_weeks',
        'anchor_starts_at',
        'end_date',
        'occurrence_count',
        'status',
        'client_id',
        'team_member_id',
        'location_id',
        'workspace_id',
        'service_template',
        'client_notes',
        'internal_notes',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'anchor_starts_at' => 'datetime',
            'end_date' => 'date',
            'interval_weeks' => 'integer',
            'occurrence_count' => 'integer',
            'service_template' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'recurrence_series_id')->orderBy('occurrence_index');
    }
}
