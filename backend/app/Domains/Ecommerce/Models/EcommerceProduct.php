<?php

namespace App\Domains\Ecommerce\Models;

use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Inventory\Models\InventoryItem;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceProduct extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'inventory_item_id',
        'title',
        'description',
        'image_url',
        'price_cents',
        'show_on_booking_carousel',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'show_on_booking_carousel' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(EcommerceOrderLine::class);
    }

    public function isActive(): bool
    {
        return $this->status === EcommerceProductStatus::ACTIVE;
    }
}
