<?php

namespace App\Domains\Booking\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentServiceLine extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'appointment_services';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'booking_service_id',
        'service_name',
        'duration_minutes',
        'price_cents',
        'pricing_tier',
        'sort_order',
        'package_entitlement_id',
        'entitlement_source',
        'entitlement_state',
        'client_package_id',
        'client_package_redemption_id',
        'covered_quantity',
        'covered_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'sort_order' => 'integer',
            'covered_quantity' => 'decimal:3',
            'covered_amount_cents' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class, 'booking_service_id');
    }
}
