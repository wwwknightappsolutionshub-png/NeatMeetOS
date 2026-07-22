<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingWorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type,
            'channel' => $this->channel,
            'status' => $this->status,
            'audience_rules' => $this->audience_rules_json ?? [],
            'template_id' => $this->template_id,
            'delay_minutes' => $this->delay_minutes,
            'cooldown_days' => $this->cooldown_days,
            'allow_repeat' => $this->allow_repeat,
            'max_executions_per_client' => $this->max_executions_per_client,
            'settings' => $this->settings_json ?? [],
            'last_triggered_at' => $this->last_triggered_at?->toIso8601String(),
            'template' => $this->whenLoaded('template', fn () => new MarketingTemplateResource($this->template)),
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'step_type' => $s->step_type,
                'delay_minutes' => $s->delay_minutes,
                'template_id' => $s->template_id,
                'channel' => $s->channel,
                'payload' => $s->payload_json ?? [],
            ])),
            'steps_count' => $this->whenCounted('steps'),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'display_name' => $this->createdBy->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
