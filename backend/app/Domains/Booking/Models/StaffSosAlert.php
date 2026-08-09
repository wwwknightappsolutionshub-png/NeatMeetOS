<?php

namespace App\Domains\Booking\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSosAlert extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const KIND_NEW_BOOKING = 'new_booking';

    public const KIND_APPROACHING = 'approaching';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'staff_sos_alerts';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'kind',
        'status',
        'title',
        'body',
        'payload_json',
        'acknowledged_at',
        'acknowledged_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
