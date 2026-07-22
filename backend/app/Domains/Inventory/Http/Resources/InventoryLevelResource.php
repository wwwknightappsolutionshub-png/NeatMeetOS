<?php

namespace App\Domains\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null),
            'item' => $this->whenLoaded('item', fn () => $this->item ? [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'sku' => $this->item->sku,
                'item_type' => $this->item->item_type,
            ] : null),
            'on_hand_quantity' => (string) $this->on_hand_quantity,
            'reserved_quantity' => (string) $this->reserved_quantity,
            'reorder_point' => $this->reorder_point !== null ? (string) $this->reorder_point : null,
            'reorder_target' => $this->reorder_target !== null ? (string) $this->reorder_target : null,
            'is_low_stock' => $this->isLowStock(),
            'last_restocked_at' => $this->last_restocked_at?->toIso8601String(),
        ];
    }
}
