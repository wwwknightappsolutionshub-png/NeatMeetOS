<?php

namespace App\Domains\Ecommerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEcommerceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'price_cents' => $this->price_cents,
            'inventory_item_id' => $this->inventory_item_id,
            'available_quantity' => $this->when(
                $this->getAttribute('available_quantity') !== null,
                (float) $this->getAttribute('available_quantity'),
            ),
        ];
    }
}
