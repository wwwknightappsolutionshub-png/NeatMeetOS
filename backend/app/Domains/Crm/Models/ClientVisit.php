<?php

namespace App\Domains\Crm\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Location;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientVisit extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'location_id',
        'checked_in_at',
        'source',
        'loyalty_points_awarded',
        'notes',
        'next_visit_appointment_id',
        'next_visit_prompted_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'loyalty_points_awarded' => 'integer',
            'next_visit_prompted_at' => 'datetime',
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

    public function nextVisitAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'next_visit_appointment_id');
    }
}
