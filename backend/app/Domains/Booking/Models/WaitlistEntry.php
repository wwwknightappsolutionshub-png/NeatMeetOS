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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WaitlistEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_UNREACHABLE = 'unreachable';

    public static function statuses(): array
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_CONTACTED,
            self::STATUS_UNREACHABLE,
            self::STATUS_BOOKED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'location_id',
        'team_member_id',
        'workspace_id',
        'workspace_type_preference',
        'preferred_starts_at',
        'preferred_ends_at',
        'availability_notes',
        'status',
        'contacted_at',
        'notes',
        'fulfilled_appointment_id',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'preferred_starts_at' => 'datetime',
            'preferred_ends_at' => 'datetime',
            'contacted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function fulfilledAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'fulfilled_appointment_id');
    }

    public function bookableServices(): BelongsToMany
    {
        return $this->belongsToMany(BookableService::class, 'waitlist_services', 'waitlist_entry_id', 'booking_service_id')
            ->withPivot(['service_name', 'sort_order']);
    }
}
