<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPackageRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_package_id' => $this->client_package_id,
            'redemption_type' => $this->redemption_type,
            'quantity' => (float) $this->quantity,
            'booking_service_id' => $this->booking_service_id,
            'appointment_id' => $this->appointment_id,
            'checkout_id' => $this->checkout_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
