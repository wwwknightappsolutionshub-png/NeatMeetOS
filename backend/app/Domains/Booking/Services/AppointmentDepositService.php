<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;

class AppointmentDepositService
{
    /**
     * @param  array<int, array{booking_service_id: string, service_name: string}>  $resolvedLines
     * @return array{deposit_status: string, deposit_required_cents: int|null, deposit_rule_snapshot: array|null}
     */
    public function snapshotFromResolvedLines(array $resolvedLines): array
    {
        $totalCents = 0;
        $breakdown = [];

        foreach ($resolvedLines as $line) {
            $service = BookableService::query()->find($line['booking_service_id']);

            if ($service === null || ! $service->deposit_required || $service->deposit_amount_cents === null) {
                continue;
            }

            $totalCents += $service->deposit_amount_cents;
            $breakdown[] = [
                'booking_service_id' => $service->id,
                'service_name' => $line['service_name'],
                'deposit_amount_cents' => $service->deposit_amount_cents,
            ];
        }

        if ($totalCents === 0) {
            return [
                'deposit_status' => Appointment::DEPOSIT_NOT_REQUIRED,
                'deposit_required_cents' => null,
                'deposit_rule_snapshot' => null,
            ];
        }

        return [
            'deposit_status' => Appointment::DEPOSIT_PENDING,
            'deposit_required_cents' => $totalCents,
            'deposit_rule_snapshot' => ['services' => $breakdown],
        ];
    }
}
