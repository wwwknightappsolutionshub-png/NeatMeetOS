<?php

namespace App\Domains\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'location_id' => $this->location_id,
            'movement_type' => $this->movement_type,
            'quantity_delta' => (string) $this->quantity_delta,
            'quantity_before' => $this->quantity_before !== null ? (string) $this->quantity_before : null,
            'quantity_after' => $this->quantity_after !== null ? (string) $this->quantity_after : null,
            'unit_cost_cents' => $this->unit_cost_cents,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'item' => $this->whenLoaded('item', fn () => $this->item ? [
                'id' => $this->item->id,
                'name' => $this->item->name,
            ] : null),
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
