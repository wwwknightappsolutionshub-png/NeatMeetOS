<?php

namespace App\Domains\Integrations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Integrations\Models\ProviderWebhookEvent */
class ProviderWebhookEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'provider_account_id' => $this->provider_account_id,
            'category' => $this->category,
            'driver' => $this->driver,
            'event_type' => $this->event_type,
            'external_event_id' => $this->external_event_id,
            'received_at' => $this->received_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'processing_status' => $this->processing_status,
            'processing_error' => $this->processing_error,
            'signature_valid' => $this->signature_valid,
            'payload' => $this->payload_json ?? (object) [],
            'headers' => $this->headers_json ?? (object) [],
            'resolved_source_domain' => $this->resolved_source_domain,
            'resolved_source_type' => $this->resolved_source_type,
            'resolved_source_id' => $this->resolved_source_id,
            'metadata' => $this->metadata_json ?? (object) [],
            'provider_account' => $this->whenLoaded('providerAccount', fn () => new ProviderAccountResource($this->providerAccount)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
