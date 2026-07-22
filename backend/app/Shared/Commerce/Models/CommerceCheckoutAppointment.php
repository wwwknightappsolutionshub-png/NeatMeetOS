<?php

namespace App\Shared\Commerce\Models;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceCheckoutAppointment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_checkout_appointments';

    public const ROLE_PRIMARY = 'primary';

    public const ROLE_ADDITIONAL = 'additional';

    protected $fillable = [
        'tenant_id',
        'checkout_id',
        'appointment_id',
        'role',
        'imported_subtotal_cents',
    ];

    protected function casts(): array
    {
        return [
            'imported_subtotal_cents' => 'integer',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'checkout_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
