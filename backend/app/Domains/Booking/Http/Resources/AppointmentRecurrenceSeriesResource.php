<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentRecurrenceSeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pattern' => $this->pattern,
            'interval_weeks' => $this->interval_weeks,
            'anchor_starts_at' => $this->anchor_starts_at?->toIso8601String(),
            'end_date' => $this->end_date?->toDateString(),
            'occurrence_count' => $this->occurrence_count,
            'status' => $this->status,
            'client_id' => $this->client_id,
            'team_member_id' => $this->team_member_id,
            'location_id' => $this->location_id,
            'workspace_id' => $this->workspace_id,
            'service_template' => $this->service_template,
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
