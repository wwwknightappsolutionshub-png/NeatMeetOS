<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'plan_type' => $this->plan_type,
            'billing_frequency' => $this->billing_frequency,
            'price_cents' => $this->price_cents,
            'joining_fee_cents' => $this->joining_fee_cents,
            'included_wallet_credit_cents' => $this->included_wallet_credit_cents,
            'included_loyalty_points' => $this->included_loyalty_points,
            'included_entitlement_quantity' => $this->included_entitlement_quantity,
            'auto_renew' => $this->auto_renew,
            'grace_period_days' => $this->grace_period_days,
            'is_public' => $this->is_public,
            'applies_to_all_locations' => $this->applies_to_all_locations,
            'notes' => $this->notes,
            'location_ids' => $this->whenLoaded('locations', fn () => $this->locations->pluck('id')),
            'locations' => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
            ])),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
