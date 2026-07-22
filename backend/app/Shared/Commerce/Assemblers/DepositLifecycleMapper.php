<?php

namespace App\Shared\Commerce\Assemblers;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Contracts\DepositSettlementContract;
use App\Shared\Commerce\DTO\DepositContractDto;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Commerce\Models\CommerceDepositRecord;

class DepositLifecycleMapper implements DepositSettlementContract
{
    public function resolveForAppointment(string $appointmentId): DepositContractDto
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);

        $record = CommerceDepositRecord::query()
            ->where('appointment_id', $appointmentId)
            ->orderByDesc('created_at')
            ->first();

        $lifecycleState = $this->mapBookingStatusToLifecycle(
            $appointment->deposit_status,
            $record,
        );

        return new DepositContractDto(
            appointmentId: $appointment->id,
            bookingDepositStatus: $appointment->deposit_status,
            lifecycleState: $lifecycleState,
            requiredCents: $appointment->deposit_required_cents,
            collectedCents: $record?->collected_cents,
            depositRecordId: $record?->id,
            ruleSnapshot: $appointment->deposit_rule_snapshot,
        );
    }

    public function mapBookingStatusToLifecycle(
        string $bookingDepositStatus,
        ?CommerceDepositRecord $record,
    ): string {
        if ($record !== null) {
            return $record->lifecycle_state;
        }

        return match ($bookingDepositStatus) {
            Appointment::DEPOSIT_NOT_REQUIRED => DepositLifecycleState::NOT_REQUIRED,
            Appointment::DEPOSIT_WAIVED => DepositLifecycleState::WAIVED,
            Appointment::DEPOSIT_SATISFIED => DepositLifecycleState::COLLECTED,
            Appointment::DEPOSIT_FAILED => DepositLifecycleState::REQUIRED,
            default => DepositLifecycleState::REQUIRED,
        };
    }
}
