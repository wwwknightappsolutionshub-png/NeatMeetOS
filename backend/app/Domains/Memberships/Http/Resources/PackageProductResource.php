<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'price_cents' => $this->price_cents,
            'included_quantity' => (float) $this->included_quantity,
            'expiry_days' => $this->expiry_days,
            'is_public' => $this->is_public,
            'notes' => $this->notes,
            'service_restrictions' => $this->whenLoaded('bookingServices', fn () => $this->bookingServices->map(fn ($s) => [
                'booking_service_id' => $s->id,
                'service_name' => $s->name,
                'quantity_per_redemption' => $s->pivot->quantity_per_redemption !== null
                    ? (float) $s->pivot->quantity_per_redemption
                    : null,
            ])),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
