<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class AppointmentRebookService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly AppointmentBookingService $bookingService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function rebook(Appointment $source, array $overrides, ?string $createdByTeamMemberId = null): Appointment
    {
        $this->scope->assertTenantModel($source);

        if ($source->serviceLines->isEmpty()) {
            throw ValidationException::withMessages([
                'appointment' => ['Source appointment has no services to rebook.'],
            ]);
        }

        $services = $source->serviceLines->map(fn ($line) => [
            'booking_service_id' => $line->booking_service_id,
            'sort_order' => $line->sort_order,
        ])->all();

        $data = [
            'client_id' => $overrides['client_id'] ?? $source->client_id,
            'team_member_id' => $overrides['team_member_id'] ?? $source->team_member_id,
            'location_id' => $overrides['location_id'] ?? $source->location_id,
            'workspace_id' => $overrides['workspace_id'] ?? $source->workspace_id,
            'starts_at' => $overrides['starts_at'],
            'status' => $overrides['status'] ?? Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ADMIN,
            'client_notes' => $overrides['client_notes'] ?? $source->client_notes,
            'internal_notes' => $overrides['internal_notes']
                ?? ($source->internal_notes ? "Rebooked from {$source->booking_reference}\n{$source->internal_notes}" : "Rebooked from {$source->booking_reference}"),
            'services' => $overrides['services'] ?? $services,
        ];

        $appointment = $this->bookingService->create($data, $createdByTeamMemberId);
        $appointment->rebooked_from_appointment_id = $source->id;
        $appointment->save();

        $this->auditLogger->log('appointment.rebooked', $appointment, null, [
            'source_appointment_id' => $source->id,
            'source_booking_reference' => $source->booking_reference,
        ]);

        return $appointment->fresh()->load([
            'client', 'teamMember', 'location', 'workspace', 'serviceLines',
        ]);
    }
}
