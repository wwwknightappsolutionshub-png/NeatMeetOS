<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\ClientConsentRecord */
class ClientConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'consent_type' => $this->consent_type,
            'granted' => $this->granted,
            'source' => $this->source,
            'actor_user_id' => $this->actor_user_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'metadata' => $this->metadata,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
