<?php

namespace App\Domains\Booking\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_WAITLIST = 'waitlist';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_NEXT_VISIT = 'next_visit';

    public const WALK_IN_WAITING = 'waiting';

    public const WALK_IN_SEATED = 'seated';

    public const DEPOSIT_NOT_REQUIRED = 'not_required';

    public const DEPOSIT_PENDING = 'pending';

    public const DEPOSIT_SATISFIED = 'satisfied';

    public const DEPOSIT_WAIVED = 'waived';

    public const DEPOSIT_FAILED = 'failed';

    public static function depositStatuses(): array
    {
        return [
            self::DEPOSIT_NOT_REQUIRED,
            self::DEPOSIT_PENDING,
            self::DEPOSIT_SATISFIED,
            self::DEPOSIT_WAIVED,
            self::DEPOSIT_FAILED,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ];
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_COMPLETED,
        ];
    }

    public static function bookingSources(): array
    {
        return [
            self::SOURCE_ADMIN,
            self::SOURCE_INTERNAL,
            self::SOURCE_WAITLIST,
            self::SOURCE_WALK_IN,
            self::SOURCE_ONLINE,
            self::SOURCE_NEXT_VISIT,
        ];
    }

    public static function walkInStages(): array
    {
        return [self::WALK_IN_WAITING, self::WALK_IN_SEATED];
    }

    public static function billingSettlementStatuses(): array
    {
        return BillingSettlementStatus::all();
    }

    protected $fillable = [
        'tenant_id',
        'location_id',
        'client_id',
        'team_member_id',
        'workspace_id',
        'starts_at',
        'ends_at',
        'status',
        'booking_source',
        'walk_in_stage',
        'arrived_at',
        'client_notes',
        'internal_notes',
        'created_by_team_member_id',
        'cancelled_at',
        'cancellation_reason',
        'no_show_reason',
        'status_correction_note',
        'rebooked_from_appointment_id',
        'recurrence_series_id',
        'occurrence_index',
        'booking_reference',
        'public_manage_token',
        'deposit_status',
        'deposit_required_cents',
        'deposit_rule_snapshot',
        'billing_settlement_status',
        'next_visit_reminded_72h_at',
        'next_visit_reminded_24h_at',
        'origin_visit_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'arrived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deposit_rule_snapshot' => 'array',
            'occurrence_index' => 'integer',
            'deposit_required_cents' => 'integer',
            'next_visit_reminded_72h_at' => 'datetime',
            'next_visit_reminded_24h_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function serviceLines(): HasMany
    {
        return $this->hasMany(AppointmentServiceLine::class)->orderBy('sort_order');
    }

    public function recurrenceSeries(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecurrenceSeries::class, 'recurrence_series_id');
    }

    public function rebookedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rebooked_from_appointment_id');
    }

    public function originVisit(): BelongsTo
    {
        return $this->belongsTo(ClientVisit::class, 'origin_visit_id');
    }

    public function isWalkInWaiting(): bool
    {
        return $this->walk_in_stage === self::WALK_IN_WAITING;
    }

    public function isBlockingSchedule(): bool
    {
        if ($this->walk_in_stage === self::WALK_IN_WAITING) {
            return false;
        }

        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_NO_SHOW], true);
    }
}
