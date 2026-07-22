<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Identity\Models\Location;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLevel extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'inventory_item_id',
        'location_id',
        'on_hand_quantity',
        'reserved_quantity',
        'reorder_point',
        'reorder_target',
        'last_restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'reorder_target' => 'decimal:3',
            'last_restocked_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_point === null) {
            return false;
        }

        return (float) $this->on_hand_quantity <= (float) $this->reorder_point;
    }
}
