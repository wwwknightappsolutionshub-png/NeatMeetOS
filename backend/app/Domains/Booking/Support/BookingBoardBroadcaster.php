<?php

namespace App\Domains\Booking\Support;

use App\Domains\Booking\Events\BookingBoardUpdated;
use App\Domains\Booking\Models\Appointment;

final class BookingBoardBroadcaster
{
    /**
     * Notify live day-board clients. Failures must never block booking mutations.
     */
    public static function forAppointment(Appointment $appointment): void
    {
        try {
            if ($appointment->tenant_id === null || $appointment->starts_at === null) {
                return;
            }

            $appointment->loadMissing('location');
            $tz = trim((string) ($appointment->location?->timezone ?? '')) ?: (string) config('app.timezone', 'UTC');

            event(new BookingBoardUpdated(
                tenantId: (string) $appointment->tenant_id,
                date: $appointment->starts_at->copy()->timezone($tz)->toDateString(),
                locationId: $appointment->location_id !== null ? (string) $appointment->location_id : null,
            ));
        } catch (\Throwable) {
            // Realtime is best-effort.
        }
    }
}
