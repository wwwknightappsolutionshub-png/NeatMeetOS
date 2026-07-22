<?php

namespace App\Domains\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Crm\Models\Client */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'resolved_display_name' => $this->resolvedDisplayName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'special_event_month' => $this->special_event_month,
            'special_event_day' => $this->special_event_day,
            'special_event_label' => $this->special_event_label,
            'primary_location_id' => $this->primary_location_id,
            'primary_location' => $this->whenLoaded('primaryLocation', fn () => [
                'id' => $this->primaryLocation->id,
                'name' => $this->primaryLocation->name,
            ]),
            'preferred_team_member_id' => $this->preferred_team_member_id,
            'preferred_team_member' => $this->whenLoaded('preferredTeamMember', fn () => $this->preferredTeamMember ? [
                'id' => $this->preferredTeamMember->id,
                'display_name' => $this->preferredTeamMember->display_name,
            ] : null),
            'internal_flags' => $this->internal_flags,
            'preferences' => $this->preferences,
            'loyalty_display_status' => $this->loyalty_display_status,
            'last_visited_at' => $this->last_visited_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'tag_ids' => $this->whenLoaded('tags', fn () => $this->tags->pluck('id')),
            'tags' => ClientTagResource::collection($this->whenLoaded('tags')),
            'active_membership' => $this->whenLoaded('memberships', function () {
                $membership = $this->memberships->first();
                if ($membership === null) {
                    return null;
                }

                return [
                    'id' => $membership->id,
                    'status' => $membership->status,
                    'plan_id' => $membership->membership_plan_id,
                    'plan_name' => $membership->membershipPlan?->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
