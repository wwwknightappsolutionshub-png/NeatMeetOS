<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Support\BookingBoardBroadcaster;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class AppointmentLifecycleService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        Appointment::STATUS_PENDING => [
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ],
        Appointment::STATUS_CONFIRMED => [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ],
        Appointment::STATUS_CHECKED_IN => [
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ],
        Appointment::STATUS_COMPLETED => [],
        Appointment::STATUS_CANCELLED => [],
        Appointment::STATUS_NO_SHOW => [],
    ];

    /** @var array<string, list<string>> */
    private const CORRECTION_TARGETS = [
        Appointment::STATUS_COMPLETED => [
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_CONFIRMED,
        ],
        Appointment::STATUS_CANCELLED => [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CONFIRMED,
        ],
        Appointment::STATUS_NO_SHOW => [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CONFIRMED,
        ],
    ];

    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly MarketingAutomationTriggerService $marketingTriggers,
        private readonly NotificationTriggerService $notificationTriggers,
    ) {}

    public function transition(
        Appointment $appointment,
        string $status,
        ?string $noShowReason = null,
    ): Appointment {
        $this->scope->assertTenantModel($appointment);

        if (! in_array($status, Appointment::statuses(), true)) {
            throw ValidationException::withMessages(['status' => ['Invalid status.']]);
        }

        if ($appointment->status === $status) {
            return $appointment;
        }

        if ($appointment->walk_in_stage === Appointment::WALK_IN_WAITING
            && $status === Appointment::STATUS_CHECKED_IN) {
            throw ValidationException::withMessages([
                'status' => ['Walk-in must be seated before check-in. Use seat walk-in action.'],
            ]);
        }

        $allowed = self::TRANSITIONS[$appointment->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$appointment->status} to {$status}."],
            ]);
        }

        $old = [
            'status' => $appointment->status,
            'no_show_reason' => $appointment->no_show_reason,
        ];

        $appointment->status = $status;

        if ($status === Appointment::STATUS_NO_SHOW) {
            $appointment->no_show_reason = $noShowReason;
        } elseif ($appointment->no_show_reason !== null && $status !== Appointment::STATUS_NO_SHOW) {
            $appointment->no_show_reason = null;
        }

        if ($status === Appointment::STATUS_CHECKED_IN && $appointment->walk_in_stage === Appointment::WALK_IN_SEATED) {
            $appointment->walk_in_stage = null;
        }

        $appointment->save();

        $action = $status === Appointment::STATUS_NO_SHOW
            ? 'appointment.marked_no_show'
            : 'appointment.status_updated';

        $this->auditLogger->log($action, $appointment, $old, [
            'status' => $status,
            'no_show_reason' => $noShowReason,
        ]);

        $appointment = $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);

        if ($status === Appointment::STATUS_CANCELLED) {
            $this->notificationTriggers->safe(
                fn () => $this->notificationTriggers->sendBookingCancellation($appointment)
            );
        }

        try {
            if ($status === Appointment::STATUS_COMPLETED) {
                $this->marketingTriggers->fireAppointmentCompleted($appointment);
            } elseif ($status === Appointment::STATUS_NO_SHOW) {
                $this->marketingTriggers->fireAppointmentNoShow($appointment);
            }
        } catch (\Throwable) {
            // Marketing automations must not block appointment transitions.
        }

        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function correctStatus(
        Appointment $appointment,
        string $status,
        string $correctionNote,
    ): Appointment {
        $this->scope->assertTenantModel($appointment);

        $allowed = self::CORRECTION_TARGETS[$appointment->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Status correction from {$appointment->status} to {$status} is not allowed."],
            ]);
        }

        $old = [
            'status' => $appointment->status,
            'status_correction_note' => $appointment->status_correction_note,
            'no_show_reason' => $appointment->no_show_reason,
        ];

        $appointment->status = $status;
        $appointment->status_correction_note = $correctionNote;
        $appointment->no_show_reason = null;

        if ($status !== Appointment::STATUS_CANCELLED) {
            $appointment->cancelled_at = null;
            $appointment->cancellation_reason = null;
        }

        $appointment->save();

        $this->auditLogger->log('appointment.status_corrected', $appointment, $old, [
            'status' => $status,
            'status_correction_note' => $correctionNote,
        ]);

        $appointment = $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);
        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function allowedTransitions(Appointment $appointment): array
    {
        $standard = self::TRANSITIONS[$appointment->status] ?? [];
        $corrections = self::CORRECTION_TARGETS[$appointment->status] ?? [];

        return array_values(array_unique([...$standard, ...$corrections]));
    }
}
