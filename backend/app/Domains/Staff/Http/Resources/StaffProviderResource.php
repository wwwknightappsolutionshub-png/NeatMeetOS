<?php

namespace App\Domains\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'employment_type' => $this->employment_type,
            'is_active' => $this->is_active,
            'primary_location_id' => $this->primary_location_id,
            'primary_location' => $this->whenLoaded('primaryLocation', fn () => $this->primaryLocation ? [
                'id' => $this->primaryLocation->id,
                'name' => $this->primaryLocation->name,
            ] : null),
            'profile' => $this->whenLoaded('staffProfile', fn () => $this->staffProfile
                ? new StaffProfileResource($this->staffProfile)
                : null),
            'is_bookable' => $this->staffProfile?->is_bookable ?? false,
            'operating_location_ids' => $this->whenLoaded('operatingLocations', fn () => $this->operatingLocations->pluck('id')),
            'operating_locations' => $this->whenLoaded('operatingLocations', fn () => $this->operatingLocations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
            ])),
            'workspace_ids' => $this->whenLoaded('workspaces', fn () => $this->workspaces->pluck('id')),
            'workspaces' => $this->whenLoaded('workspaces', fn () => $this->workspaces->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'workspace_type' => $w->workspace_type,
                'location_id' => $w->location_id,
            ])),
        ];
    }
}
