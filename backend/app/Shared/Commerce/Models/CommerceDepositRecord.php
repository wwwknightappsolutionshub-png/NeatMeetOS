<?php

namespace App\Shared\Commerce\Models;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceDepositRecord extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_deposit_records';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'booking_deposit_status',
        'required_cents',
        'collected_cents',
        'lifecycle_state',
        'payment_transaction_id',
        'refunded_payment_transaction_id',
        'applied_checkout_id',
        'rule_snapshot',
        'collected_at',
        'refunded_at',
        'failure_code',
        'failure_message',
        'manual_notes',
    ];

    protected function casts(): array
    {
        return [
            'required_cents' => 'integer',
            'collected_cents' => 'integer',
            'rule_snapshot' => 'array',
            'collected_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public static function lifecycleStates(): array
    {
        return DepositLifecycleState::all();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function appliedCheckout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'applied_checkout_id');
    }
}
