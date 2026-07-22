<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookableServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'duration_minutes' => $this->duration_minutes,
            'base_price_cents' => $this->base_price_cents,
            'membership_price_cents' => $this->membership_price_cents,
            'loyalty_price_cents' => $this->loyalty_price_cents,
            'is_active' => $this->is_active,
            'is_bookable_online' => $this->is_bookable_online,
            'display_order' => $this->display_order,
            'deposit_required' => $this->deposit_required,
            'deposit_amount_cents' => $this->deposit_amount_cents,
            'min_lead_time_hours' => $this->min_lead_time_hours,
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
