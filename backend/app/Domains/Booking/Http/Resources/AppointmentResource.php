<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ]),
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'resolved_display_name' => $this->client->resolvedDisplayName(),
            ]),
            'team_member_id' => $this->team_member_id,
            'team_member' => $this->whenLoaded('teamMember', fn () => [
                'id' => $this->teamMember->id,
                'display_name' => $this->teamMember->display_name,
            ]),
            'workspace_id' => $this->workspace_id,
            'workspace' => $this->whenLoaded('workspace', fn () => $this->workspace ? [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'workspace_type' => $this->workspace->workspace_type,
            ] : null),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'booking_source' => $this->booking_source,
            'walk_in_stage' => $this->walk_in_stage,
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'client_notes' => $this->client_notes,
            'internal_notes' => $this->internal_notes,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'no_show_reason' => $this->no_show_reason,
            'status_correction_note' => $this->status_correction_note,
            'rebooked_from_appointment_id' => $this->rebooked_from_appointment_id,
            'booking_reference' => $this->booking_reference,
            'recurrence_series_id' => $this->recurrence_series_id,
            'occurrence_index' => $this->occurrence_index,
            'deposit_status' => $this->deposit_status,
            'deposit_required_cents' => $this->deposit_required_cents,
            'deposit_rule_snapshot' => $this->deposit_rule_snapshot,
            'billing_settlement_status' => $this->billing_settlement_status,
            'recurrence_series' => $this->whenLoaded('recurrenceSeries', fn () => $this->recurrenceSeries ? [
                'id' => $this->recurrenceSeries->id,
                'pattern' => $this->recurrenceSeries->pattern,
                'status' => $this->recurrenceSeries->status,
            ] : null),
            'services' => AppointmentServiceLineResource::collection($this->whenLoaded('serviceLines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
