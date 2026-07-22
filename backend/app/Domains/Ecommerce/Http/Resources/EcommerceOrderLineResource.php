<?php

namespace App\Domains\Ecommerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ecommerce_product_id' => $this->ecommerce_product_id,
            'inventory_item_id' => $this->inventory_item_id,
            'title_snapshot' => $this->title_snapshot,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'line_total_cents' => $this->line_total_cents,
        ];
    }
}
