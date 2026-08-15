<?php

namespace App\Domains\Booking\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingChangeRequest extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_CANCEL = 'cancel';

    public const TYPE_POSTPONE = 'postpone';

    public const INITIATED_BY_CUSTOMER = 'customer';

    public const INITIATED_BY_TENANT = 'tenant';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_AUTO_ACCEPTED = 'auto_accepted';

    public const RESOLVED_VIA_LINK = 'link';

    public const RESOLVED_VIA_ADMIN = 'admin';

    public const RESOLVED_VIA_AUTO = 'auto';

    protected $table = 'booking_change_requests';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'type',
        'initiated_by',
        'status',
        'decline_allowed',
        'late_fee_applies',
        'late_fee_cents',
        'proposed_starts_at',
        'proposed_ends_at',
        'proposed_team_member_id',
        'proposed_workspace_id',
        'reason',
        'action_token',
        'reminder_count',
        'last_reminded_at',
        'resolved_at',
        'resolved_via',
        'resolved_by_team_member_id',
        'staff_sos_alert_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'decline_allowed' => 'boolean',
            'late_fee_applies' => 'boolean',
            'late_fee_cents' => 'integer',
            'proposed_starts_at' => 'datetime',
            'proposed_ends_at' => 'datetime',
            'reminder_count' => 'integer',
            'last_reminded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function proposedTeamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'proposed_team_member_id');
    }

    public function staffSosAlert(): BelongsTo
    {
        return $this->belongsTo(StaffSosAlert::class, 'staff_sos_alert_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
