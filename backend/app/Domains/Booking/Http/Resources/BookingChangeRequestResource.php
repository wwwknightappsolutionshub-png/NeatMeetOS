<?php

namespace App\Domains\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domains\Booking\Models\BookingChangeRequest */
class BookingChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $links = null;
        if ($this->relationLoaded('appointment') || $this->appointment_id) {
            try {
                $links = app(\App\Domains\Booking\Services\BookingChangeRequestService::class)
                    ->actionLinks($this->resource);
            } catch (\Throwable) {
                $links = null;
            }
        }

        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'type' => $this->type,
            'initiated_by' => $this->initiated_by,
            'status' => $this->status,
            'decline_allowed' => $this->decline_allowed,
            'late_fee_applies' => $this->late_fee_applies,
            'late_fee_cents' => $this->late_fee_cents,
            'proposed_starts_at' => $this->proposed_starts_at?->toIso8601String(),
            'proposed_ends_at' => $this->proposed_ends_at?->toIso8601String(),
            'proposed_team_member_id' => $this->proposed_team_member_id,
            'reason' => $this->reason,
            'reminder_count' => $this->reminder_count,
            'last_reminded_at' => $this->last_reminded_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_via' => $this->resolved_via,
            'action_links' => $links,
            'appointment' => $this->whenLoaded('appointment', function () {
                $appt = $this->appointment;

                return [
                    'id' => $appt->id,
                    'booking_reference' => $appt->booking_reference,
                    'status' => $appt->status,
                    'starts_at' => $appt->starts_at?->toIso8601String(),
                    'ends_at' => $appt->ends_at?->toIso8601String(),
                    'client' => $appt->relationLoaded('client') && $appt->client
                        ? [
                            'id' => $appt->client->id,
                            'display_name' => $appt->client->resolvedDisplayName(),
                            'phone' => $appt->client->phone,
                        ]
                        : null,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
