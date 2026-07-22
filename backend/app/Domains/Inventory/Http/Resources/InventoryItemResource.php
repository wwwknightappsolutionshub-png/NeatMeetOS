<?php

namespace App\Domains\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'item_type' => $this->item_type,
            'status' => $this->status,
            'brand' => $this->brand,
            'category' => $this->category,
            'description' => $this->description,
            'unit_label' => $this->unit_label,
            'unit_size' => $this->unit_size,
            'cost_price_cents' => $this->cost_price_cents,
            'retail_price_cents' => $this->retail_price_cents,
            'tax_code' => $this->tax_code,
            'preferred_supplier_id' => $this->preferred_supplier_id,
            'preferred_supplier' => new InventorySupplierResource($this->whenLoaded('preferredSupplier')),
            'barcode' => $this->barcode,
            'metadata' => $this->metadata,
            'levels' => InventoryLevelResource::collection($this->whenLoaded('levels')),
            'consumption_rules' => ServiceConsumptionRuleResource::collection($this->whenLoaded('consumptionRules')),
            'is_low_stock' => $this->whenLoaded('levels', function () {
                return $this->levels->contains(fn ($level) => $level->isLowStock());
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
