<?php

namespace App\Domains\Ecommerce\Models;

use App\Domains\Ecommerce\Enums\EcommerceOrderStatus;
use App\Domains\Ecommerce\Enums\EcommercePaymentMethod;
use App\Domains\Ecommerce\Enums\EcommercePaymentStatus;
use App\Domains\Identity\Models\Location;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrder extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'order_number',
        'status',
        'payment_method',
        'payment_status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'subtotal_cents',
        'total_cents',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceOrderLine::class, 'order_id');
    }

    public function isPendingPickup(): bool
    {
        return $this->status === EcommerceOrderStatus::PENDING_PICKUP;
    }

    public function isCashInSalon(): bool
    {
        return $this->payment_method === EcommercePaymentMethod::CASH_IN_SALON;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === EcommercePaymentStatus::PAID_AT_PICKUP;
    }
}
