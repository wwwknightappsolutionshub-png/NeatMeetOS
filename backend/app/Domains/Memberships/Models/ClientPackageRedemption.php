<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPackageRedemption extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_package_id',
        'client_id',
        'booking_service_id',
        'appointment_id',
        'checkout_id',
        'redemption_type',
        'state',
        'quantity',
        'notes',
        'appointment_service_line_id',
        'checkout_line_id',
        'reserved_at',
        'redeemed_at',
        'restored_at',
        'released_at',
        'restoration_reason',
        'unit_value_cents',
        'covered_amount_cents',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_value_cents' => 'integer',
            'covered_amount_cents' => 'integer',
            'reserved_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'restored_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bookingService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class, 'booking_service_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'checkout_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }
}
