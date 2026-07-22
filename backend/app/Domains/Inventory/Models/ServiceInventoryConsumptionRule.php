<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Booking\Models\BookableService;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInventoryConsumptionRule extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'booking_service_id',
        'inventory_item_id',
        'quantity_required',
        'consumption_mode',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function bookingService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class, 'booking_service_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
