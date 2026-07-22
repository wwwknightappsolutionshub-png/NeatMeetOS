<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'campaign_type' => $this->campaign_type,
            'trigger_type' => $this->trigger_type,
            'channel' => $this->channel,
            'status' => $this->status,
            'template_id' => $this->template_id,
            'audience_name' => $this->audience_name,
            'audience_rules' => $this->audience_rules_json ?? [],
            'location_id' => $this->location_id,
            'created_by_team_member_id' => $this->created_by_team_member_id,
            'notes' => $this->notes,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'template' => $this->whenLoaded('template', fn () => new MarketingTemplateResource($this->template)),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'display_name' => $this->createdBy->display_name,
            ]),
            'runs_count' => $this->whenCounted('runs'),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
