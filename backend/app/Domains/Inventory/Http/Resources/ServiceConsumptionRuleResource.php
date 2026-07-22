<?php

namespace App\Domains\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceConsumptionRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_service_id' => $this->booking_service_id,
            'inventory_item_id' => $this->inventory_item_id,
            'quantity_required' => (string) $this->quantity_required,
            'consumption_mode' => $this->consumption_mode,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'booking_service' => $this->whenLoaded('bookingService', fn () => $this->bookingService ? [
                'id' => $this->bookingService->id,
                'name' => $this->bookingService->name,
            ] : null),
            'inventory_item' => $this->whenLoaded('inventoryItem', fn () => $this->inventoryItem ? [
                'id' => $this->inventoryItem->id,
                'name' => $this->inventoryItem->name,
                'unit_label' => $this->inventoryItem->unit_label,
            ] : null),
        ];
    }
}
