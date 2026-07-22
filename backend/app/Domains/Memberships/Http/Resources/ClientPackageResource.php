<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->resolvedDisplayName()),
            'package_product_id' => $this->package_product_id,
            'package_name' => $this->whenLoaded('packageProduct', fn () => $this->packageProduct->name),
            'status' => $this->status,
            'source' => $this->source,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'quantity_total' => (float) $this->quantity_total,
            'quantity_remaining' => (float) $this->quantity_remaining,
            'notes' => $this->notes,
            'redemptions' => ClientPackageRedemptionResource::collection($this->whenLoaded('redemptions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
