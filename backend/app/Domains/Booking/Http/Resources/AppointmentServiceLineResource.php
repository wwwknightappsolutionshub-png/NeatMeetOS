<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentServiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_service_id' => $this->booking_service_id,
            'service_name' => $this->service_name,
            'duration_minutes' => $this->duration_minutes,
            'price_cents' => $this->price_cents,
            'pricing_tier' => $this->pricing_tier,
            'sort_order' => $this->sort_order,
            'package_entitlement_id' => $this->package_entitlement_id,
            'entitlement_source' => $this->entitlement_source,
        ];
    }
}
