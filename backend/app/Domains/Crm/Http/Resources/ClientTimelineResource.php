<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\ClientTimelineEvent */
class ClientTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'payload' => $this->payload,
            'actor_user_id' => $this->actor_user_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
