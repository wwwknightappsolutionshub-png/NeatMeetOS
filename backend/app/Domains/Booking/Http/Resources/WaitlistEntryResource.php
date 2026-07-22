<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'resolved_display_name' => $this->client->resolvedDisplayName(),
            ]),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ]),
            'team_member_id' => $this->team_member_id,
            'team_member' => $this->whenLoaded('teamMember', fn () => $this->teamMember ? [
                'id' => $this->teamMember->id,
                'display_name' => $this->teamMember->display_name,
            ] : null),
            'workspace_id' => $this->workspace_id,
            'workspace_type_preference' => $this->workspace_type_preference,
            'preferred_starts_at' => $this->preferred_starts_at?->toIso8601String(),
            'preferred_ends_at' => $this->preferred_ends_at?->toIso8601String(),
            'availability_notes' => $this->availability_notes,
            'status' => $this->status,
            'contacted_at' => $this->contacted_at?->toIso8601String(),
            'notes' => $this->notes,
            'fulfilled_appointment_id' => $this->fulfilled_appointment_id,
            'services' => $this->whenLoaded('bookableServices', fn () => $this->bookableServices->map(fn ($s) => [
                'booking_service_id' => $s->id,
                'service_name' => $s->pivot->service_name,
                'sort_order' => $s->pivot->sort_order,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
