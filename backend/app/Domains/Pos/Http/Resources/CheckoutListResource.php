<?php

namespace App\Domains\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Shared\Commerce\Models\CommerceCheckout */
class CheckoutListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checkout_number' => $this->checkout_number,
            'status' => $this->status,
            'source' => $this->source,
            'client_name' => $this->client
                ? trim($this->client->first_name.' '.$this->client->last_name)
                : null,
            'location_name' => $this->location?->name,
            'cashier_name' => $this->teamMember?->display_name,
            'total_cents' => $this->total_cents,
            'amount_due_cents' => $this->amount_due_cents ?? 0,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
