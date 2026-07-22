<?php

namespace App\Domains\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_member_id' => $this->team_member_id,
            'is_bookable' => $this->is_bookable,
            'show_in_online_booking' => $this->show_in_online_booking,
            'accepts_walk_ins' => $this->accepts_walk_ins,
            'booking_display_name' => $this->booking_display_name,
            'internal_notes' => $this->internal_notes,
            'default_workspace_id' => $this->default_workspace_id,
            'default_workspace' => $this->whenLoaded('defaultWorkspace', fn () => $this->defaultWorkspace ? [
                'id' => $this->defaultWorkspace->id,
                'name' => $this->defaultWorkspace->name,
                'workspace_type' => $this->defaultWorkspace->workspace_type,
            ] : null),
            'min_lead_time_minutes' => $this->min_lead_time_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
