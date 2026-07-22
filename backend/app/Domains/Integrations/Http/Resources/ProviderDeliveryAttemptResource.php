<?php

namespace App\Domains\Integrations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Integrations\Models\ProviderDeliveryAttempt */
class ProviderDeliveryAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_account_id' => $this->provider_account_id,
            'category' => $this->category,
            'source_domain' => $this->source_domain,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'related_client_id' => $this->related_client_id,
            'related_appointment_id' => $this->related_appointment_id,
            'related_payment_transaction_id' => $this->related_payment_transaction_id,
            'direction' => $this->direction,
            'purpose' => $this->purpose,
            'recipient_address' => $this->recipient_address,
            'recipient_phone' => $this->recipient_phone,
            'subject' => $this->subject,
            'payload' => $this->payload_json ?? (object) [],
            'provider_reference' => $this->provider_reference,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'metadata' => $this->metadata_json ?? (object) [],
            'provider_account' => $this->whenLoaded('providerAccount', fn () => new ProviderAccountResource($this->providerAccount)),
            'related_client' => $this->whenLoaded('relatedClient', fn () => [
                'id' => $this->relatedClient?->id,
                'name' => $this->relatedClient?->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
