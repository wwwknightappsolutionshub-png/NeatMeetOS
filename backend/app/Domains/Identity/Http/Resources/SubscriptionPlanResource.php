<?php

namespace App\Domains\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Identity\Models\SubscriptionPlan */
class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'billing_interval' => $this->billing_interval,
            'features' => $this->features,
            'limits' => $this->limits,
            'display_price_cents' => $this->display_price_cents,
            'is_active' => $this->is_active,
            'can_subscribe' => $this->resource->getAttribute('can_subscribe'),
            'locked_reason' => $this->resource->getAttribute('locked_reason'),
        ];
    }
}
