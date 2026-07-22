<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingContactSuppressionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'channel' => $this->channel,
            'contact_value' => $this->contact_value,
            'reason' => $this->reason,
            'source' => $this->source,
            'is_active' => $this->is_active,
            'lifted_at' => $this->lifted_at?->toIso8601String(),
            'notes' => $this->notes,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'display_name' => $this->client->resolvedDisplayName(),
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'display_name' => $this->createdBy->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
