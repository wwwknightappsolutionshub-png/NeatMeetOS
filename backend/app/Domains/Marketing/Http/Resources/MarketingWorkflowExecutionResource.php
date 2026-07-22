<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingWorkflowExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'client_id' => $this->client_id,
            'campaign_id' => $this->campaign_id,
            'trigger_type' => $this->trigger_type,
            'trigger_reference_type' => $this->trigger_reference_type,
            'trigger_reference_id' => $this->trigger_reference_id,
            'status' => $this->status,
            'current_step_position' => $this->current_step_position,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'context' => $this->context_json ?? [],
            'workflow' => $this->whenLoaded('workflow', fn () => new MarketingWorkflowResource($this->workflow)),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'display_name' => $this->client->resolvedDisplayName(),
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
            ]),
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'step_type' => $s->step_type,
                'status' => $s->status,
                'scheduled_for' => $s->scheduled_for?->toIso8601String(),
                'processed_at' => $s->processed_at?->toIso8601String(),
                'failure_reason' => $s->failure_reason,
                'message_id' => $s->message_id,
                'message' => $s->relationLoaded('message') && $s->message
                    ? new MarketingMessageResource($s->message)
                    : null,
            ])),
            'messages' => $this->whenLoaded('messages', fn () => MarketingMessageResource::collection($this->messages)),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
