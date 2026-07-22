<?php

namespace App\Domains\Identity\Http\Resources;

use App\Domains\Identity\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => new LocationResource($this->location)),
            'name' => $this->name,
            'code' => $this->code,
            'workspace_type' => $this->workspace_type,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
