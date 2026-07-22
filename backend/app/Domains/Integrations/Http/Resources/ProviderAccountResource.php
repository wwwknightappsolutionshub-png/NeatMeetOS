<?php

namespace App\Domains\Integrations\Http\Resources;

use App\Domains\Integrations\Services\ProviderCredentialValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Integrations\Models\ProviderAccount */
class ProviderAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'driver' => $this->driver,
            'status' => $this->status,
            'is_default' => (bool) $this->is_default,
            'configuration' => $this->configuration_json ?? (object) [],
            'has_credentials' => ! empty($this->credentials_json),
            'config_summary' => app(ProviderCredentialValidator::class)->configSummary($this->resource),
            'from_name' => $this->from_name,
            'from_address' => $this->from_address,
            'reply_to' => $this->reply_to,
            'phone_number' => $this->phone_number,
            'metadata' => $this->metadata_json ?? (object) [],
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
            'last_test_result' => $this->last_test_result,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->display_name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->display_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
