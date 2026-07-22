<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Token-gated public manage/cancel for online (and other) bookings.
 */
class PublicBookingManageService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentBookingService $appointments,
    ) {}

    public function findByReferenceAndToken(string $bookingReference, string $token): Appointment
    {
        $appointment = Appointment::query()
            ->with(['client', 'teamMember', 'location', 'workspace', 'serviceLines'])
            ->where('booking_reference', $bookingReference)
            ->where('public_manage_token', $token)
            ->first();

        if ($appointment === null) {
            throw ValidationException::withMessages([
                'token' => ['Booking not found or manage link is invalid.'],
            ]);
        }

        return $appointment;
    }

    public function cancel(string $bookingReference, string $token, ?string $reason = null): Appointment
    {
        $appointment = $this->findByReferenceAndToken($bookingReference, $token);

        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW], true)) {
            throw ValidationException::withMessages([
                'appointment' => ['This booking can no longer be cancelled online.'],
            ]);
        }

        if ($appointment->starts_at !== null && $appointment->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'appointment' => ['Past appointments cannot be cancelled online.'],
            ]);
        }

        return $this->appointments->cancel($appointment, $reason ?? 'Cancelled by customer via manage link');
    }

    /**
     * @return array{manage_path: string, manage_url: string}
     */
    public function manageLinks(Appointment $appointment): array
    {
        $tenant = $this->tenantContext->get();
        $slug = $tenant?->slug ?? '';
        $path = '/book/'.$slug.'/manage?ref='.urlencode((string) $appointment->booking_reference)
            .'&token='.urlencode((string) $appointment->public_manage_token);

        return [
            'manage_path' => $path,
            'manage_url' => rtrim((string) config('app.frontend_url'), '/').$path,
        ];
    }
}
