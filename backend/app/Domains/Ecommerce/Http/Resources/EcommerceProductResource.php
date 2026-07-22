<?php

namespace App\Domains\Ecommerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => $this->whenLoaded('inventoryItem', fn () => [
                'id' => $this->inventoryItem->id,
                'name' => $this->inventoryItem->name,
                'sku' => $this->inventoryItem->sku,
                'item_type' => $this->inventoryItem->item_type,
                'status' => $this->inventoryItem->status,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'price_cents' => $this->price_cents,
            'show_on_booking_carousel' => $this->show_on_booking_carousel,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
