<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Identity\Models\Location;
use App\Domains\Inventory\Enums\InventoryItemStatus;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'item_type',
        'status',
        'brand',
        'category',
        'description',
        'unit_label',
        'unit_size',
        'cost_price_cents',
        'retail_price_cents',
        'tax_code',
        'preferred_supplier_id',
        'barcode',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_price_cents' => 'integer',
            'retail_price_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'preferred_supplier_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function consumptionRules(): HasMany
    {
        return $this->hasMany(ServiceInventoryConsumptionRule::class);
    }

    public function isActive(): bool
    {
        return $this->status === InventoryItemStatus::ACTIVE;
    }

    public function isRetail(): bool
    {
        return $this->item_type === InventoryItemType::RETAIL;
    }
}
