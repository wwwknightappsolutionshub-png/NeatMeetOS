<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'marketing_campaign_id' => $this->marketing_campaign_id,
            'trigger_type' => $this->trigger_type,
            'run_source' => $this->run_source,
            'status' => $this->status,
            'filters' => $this->filters_json ?? [],
            'summary' => $this->summary_json ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_by_team_member_id' => $this->created_by_team_member_id,
            'campaign' => $this->whenLoaded('campaign', fn () => new MarketingCampaignResource($this->campaign)),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'display_name' => $this->createdBy->display_name,
            ]),
            'messages' => MarketingMessageResource::collection($this->whenLoaded('messages')),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
