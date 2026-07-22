<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->resolvedDisplayName()),
            'membership_plan_id' => $this->membership_plan_id,
            'plan_name' => $this->whenLoaded('membershipPlan', fn () => $this->membershipPlan->name),
            'status' => $this->status,
            'source' => $this->source,
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_starts_at' => $this->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $this->current_period_ends_at?->toIso8601String(),
            'next_billing_date' => $this->next_billing_date?->toDateString(),
            'billing_anchor_date' => $this->billing_anchor_date?->toDateString(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'paused_at' => $this->paused_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'price_cents_snapshot' => $this->price_cents_snapshot,
            'joining_fee_cents_snapshot' => $this->joining_fee_cents_snapshot,
            'included_wallet_credit_cents_snapshot' => $this->included_wallet_credit_cents_snapshot,
            'included_loyalty_points_snapshot' => $this->included_loyalty_points_snapshot,
            'included_entitlement_quantity_snapshot' => $this->included_entitlement_quantity_snapshot,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
