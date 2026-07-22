<?php

namespace App\Domains\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Identity\Models\Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id')),
            'team_member_ids' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->pluck('id')),
            'team_member_count' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->count()),
        ];
    }
}
