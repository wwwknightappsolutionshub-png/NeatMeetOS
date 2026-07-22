<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\ClientDocument */
class ClientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'document_type' => $this->document_type,
            'storage_path' => $this->storage_path,
            'description' => $this->description,
            'uploaded_by_team_member_id' => $this->uploaded_by_team_member_id,
            'uploaded_by_name' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy?->display_name),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
