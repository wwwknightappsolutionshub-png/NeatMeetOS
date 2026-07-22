<?php

namespace App\Domains\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'marketing_campaign_id' => $this->marketing_campaign_id,
            'marketing_run_id' => $this->marketing_run_id,
            'workflow_execution_id' => $this->workflow_execution_id,
            'workflow_step_id' => $this->workflow_step_id,
            'client_id' => $this->client_id,
            'appointment_id' => $this->appointment_id,
            'membership_id' => $this->membership_id,
            'location_id' => $this->location_id,
            'channel' => $this->channel,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'recipient_address' => $this->recipient_address,
            'subject' => $this->subject,
            'rendered_body_text' => $this->rendered_body_text,
            'rendered_body_html' => $this->rendered_body_html,
            'template_snapshot' => $this->template_snapshot_json ?? [],
            'variables_snapshot' => $this->variables_snapshot_json ?? [],
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'clicked_at' => $this->clicked_at?->toIso8601String(),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'suppressed_at' => $this->suppressed_at?->toIso8601String(),
            'skipped_reason' => $this->skipped_reason,
            'provider_message_reference' => $this->provider_message_reference,
            'provider_message_id' => $this->provider_message_id,
            'failure_category' => $this->failure_category,
            'error_message' => $this->error_message,
            'attempts' => $this->whenLoaded('attempts', fn () => $this->attempts->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'provider' => $a->provider,
                'provider_reference' => $a->provider_reference,
                'failure_category' => $a->failure_category,
                'attempted_at' => $a->attempted_at?->toIso8601String(),
                'error_message' => $a->error_message,
            ])),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'display_name' => $this->client->display_name,
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
