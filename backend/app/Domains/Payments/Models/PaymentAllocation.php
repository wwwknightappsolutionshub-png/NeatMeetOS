<?php

namespace App\Domains\Payments\Models;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceDepositRecord;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'payment_transaction_id',
        'allocation_type',
        'amount_cents',
        'commerce_checkout_id',
        'appointment_id',
        'commerce_deposit_record_id',
        'notes',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function depositRecord(): BelongsTo
    {
        return $this->belongsTo(CommerceDepositRecord::class, 'commerce_deposit_record_id');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CommerceCheckout::class, 'commerce_checkout_id');
    }
}
