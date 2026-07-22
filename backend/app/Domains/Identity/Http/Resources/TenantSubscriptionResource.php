<?php

namespace App\Domains\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Identity\Models\TenantSubscription */
class TenantSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'status' => $this->status,
            'billing_interval' => $this->billing_interval,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'provider' => $this->provider,
            'desired_plan_slug' => $this->desired_plan_slug,
            'tier_unlocked' => (bool) $this->tier_unlocked,
            'tier_unlocked_at' => $this->tier_unlocked_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
        ];
    }
}
