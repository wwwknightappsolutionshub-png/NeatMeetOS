<?php

namespace App\Shared\Commerce\Services;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Enums\BillingSettlementStatus;

class AppointmentCheckoutEligibilityValidator
{
    /**
     * @return array{eligible: bool, reason?: string}
     */
    public function validate(Appointment $appointment): array
    {
        if ($appointment->walk_in_stage === Appointment::WALK_IN_WAITING) {
            return [
                'eligible' => false,
                'reason' => 'Walk-in must be seated before checkout.',
            ];
        }

        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true)) {
            return [
                'eligible' => false,
                'reason' => 'Cancelled and no-show appointments cannot be checked out.',
            ];
        }

        if (! in_array($appointment->status, [Appointment::STATUS_CHECKED_IN, Appointment::STATUS_COMPLETED], true)) {
            return [
                'eligible' => false,
                'reason' => 'Appointment must be checked in or completed for service checkout.',
            ];
        }

        if ($appointment->billing_settlement_status === BillingSettlementStatus::SETTLED) {
            return [
                'eligible' => false,
                'reason' => 'Appointment is already fully settled.',
            ];
        }

        if ($appointment->relationLoaded('serviceLines')) {
            if ($appointment->serviceLines->isEmpty()) {
                return [
                    'eligible' => false,
                    'reason' => 'Appointment has no billable service lines.',
                ];
            }
        } elseif ($appointment->serviceLines()->count() === 0) {
            return [
                'eligible' => false,
                'reason' => 'Appointment has no billable service lines.',
            ];
        }

        return ['eligible' => true];
    }
}
