<?php

namespace App\Domains\Identity\Http\Resources;

use App\Domains\Identity\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeamMember */
class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'phone' => $this->phone,
            'employment_type' => $this->employment_type,
            'primary_location_id' => $this->primary_location_id,
            'primary_location' => $this->whenLoaded('primaryLocation', fn () => new LocationResource($this->primaryLocation)),
            'workspace_ids' => $this->whenLoaded('workspaces', fn () => $this->workspaces->pluck('id')),
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')),
            'roles' => $this->whenLoaded('roles', fn () => RoleResource::collection($this->roles)),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
