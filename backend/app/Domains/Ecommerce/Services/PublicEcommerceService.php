<?php

namespace App\Domains\Ecommerce\Services;

use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Ecommerce\Models\EcommerceProduct;
use App\Domains\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Collection;

class PublicEcommerceService
{
    public function listProducts(array $filters = []): Collection
    {
        $query = EcommerceProduct::query()
            ->with('inventoryItem')
            ->where('status', EcommerceProductStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('title');

        if (! empty($filters['carousel'])) {
            $query->where('show_on_booking_carousel', true);
        }

        // Always return active carousel products; stock is annotated by the controller.
        // Do not hide out-of-stock items from the booking page (buy button disables instead).
        return $query->limit(100)->get();
    }

    public function availableQuantity(string $inventoryItemId, string $locationId): float
    {
        $level = InventoryLevel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('location_id', $locationId)
            ->first();

        return $level !== null ? (float) $level->on_hand_quantity : 0.0;
    }
}
