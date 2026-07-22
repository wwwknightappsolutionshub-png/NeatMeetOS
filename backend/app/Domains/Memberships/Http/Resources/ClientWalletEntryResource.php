<?php

namespace App\Domains\Memberships\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientWalletEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->resolvedDisplayName()),
            'entry_type' => $this->entry_type,
            'direction' => $this->direction,
            'amount_cents' => $this->amount_cents,
            'balance_effective_at' => $this->balance_effective_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
