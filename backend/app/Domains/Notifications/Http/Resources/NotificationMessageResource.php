<?php

namespace App\Domains\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'appointment_id' => $this->appointment_id,
            'checkout_id' => $this->checkout_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'client_membership_id' => $this->client_membership_id,
            'marketing_workflow_execution_id' => $this->marketing_workflow_execution_id,
            'notification_template_id' => $this->notification_template_id,
            'source_type' => $this->source_type,
            'purpose' => $this->purpose,
            'channel' => $this->channel,
            'direction' => $this->direction,
            'status' => $this->status,
            'recipient_name' => $this->recipient_name,
            'recipient_address' => $this->recipient_address,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'metadata' => $this->metadata ?? [],
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'attempts' => NotificationMessageAttemptResource::collection($this->whenLoaded('attempts')),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'display_name' => $this->client?->resolvedDisplayName(),
                'email' => $this->client?->email,
                'phone' => $this->client?->phone,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'display_name' => $this->createdBy->display_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
