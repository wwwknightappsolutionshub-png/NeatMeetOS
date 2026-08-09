<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\StaffSosAlert;
use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffSosAlertService
{
    public const SHIFT_MINUTES = [10, 20, 30, 45];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MemberPushDispatchService $push,
        private readonly AppointmentBookingService $appointments,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, StaffSosAlert>
     */
    public function listActive(): Collection
    {
        return StaffSosAlert::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines'])
            ->where('status', StaffSosAlert::STATUS_ACTIVE)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(string $id): StaffSosAlert
    {
        return StaffSosAlert::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines'])
            ->findOrFail($id);
    }

    public function raiseForNewOnlineBooking(Appointment $appointment): StaffSosAlert
    {
        $appointment->loadMissing(['client', 'teamMember', 'location', 'serviceLines']);
        $when = $appointment->starts_at?->toDayDateTimeString() ?? 'soon';
        $client = $appointment->client?->resolvedDisplayName() ?? 'Client';
        $ref = $appointment->booking_reference ?? $appointment->id;
        $services = $appointment->serviceLines->pluck('service_name')->filter()->implode(', ') ?: 'Service';

        $alert = StaffSosAlert::query()->create([
            'tenant_id' => $this->tenantContext->id(),
            'appointment_id' => $appointment->id,
            'kind' => StaffSosAlert::KIND_NEW_BOOKING,
            'status' => StaffSosAlert::STATUS_ACTIVE,
            'title' => 'SOS · New online booking',
            'body' => "{$client} booked {$services} for {$when}. Ref {$ref}.",
            'payload_json' => [
                'appointment_id' => $appointment->id,
                'booking_reference' => $appointment->booking_reference,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'allow_shift' => false,
            ],
        ]);

        $this->dispatchPush($alert);
        $this->auditLogger->log('staff_sos.raised', $alert, null, [
            'kind' => $alert->kind,
            'appointment_id' => $appointment->id,
        ]);

        return $alert;
    }

    public function raiseApproaching(Appointment $appointment): ?StaffSosAlert
    {
        $exists = StaffSosAlert::query()
            ->where('appointment_id', $appointment->id)
            ->where('kind', StaffSosAlert::KIND_APPROACHING)
            ->where('status', StaffSosAlert::STATUS_ACTIVE)
            ->exists();

        if ($exists) {
            return null;
        }

        $appointment->loadMissing(['client', 'teamMember', 'location', 'serviceLines']);
        $when = $appointment->starts_at?->format('g:i A') ?? 'soon';
        $client = $appointment->client?->resolvedDisplayName() ?? 'Client';
        $services = $appointment->serviceLines->pluck('service_name')->filter()->implode(', ') ?: 'Service';

        $alert = StaffSosAlert::query()->create([
            'tenant_id' => $this->tenantContext->id(),
            'appointment_id' => $appointment->id,
            'kind' => StaffSosAlert::KIND_APPROACHING,
            'status' => StaffSosAlert::STATUS_ACTIVE,
            'title' => 'SOS · Appointment in 20 minutes',
            'body' => "{$client} — {$services} at {$when}. Shift forward if you are running late.",
            'payload_json' => [
                'appointment_id' => $appointment->id,
                'booking_reference' => $appointment->booking_reference,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'allow_shift' => true,
                'shift_minutes' => self::SHIFT_MINUTES,
            ],
        ]);

        $this->dispatchPush($alert);
        $this->auditLogger->log('staff_sos.raised', $alert, null, [
            'kind' => $alert->kind,
            'appointment_id' => $appointment->id,
        ]);

        return $alert;
    }

    public function acknowledge(StaffSosAlert $alert, ?string $teamMemberId = null): StaffSosAlert
    {
        if ($alert->status !== StaffSosAlert::STATUS_ACTIVE) {
            return $alert;
        }

        $alert->status = StaffSosAlert::STATUS_ACKNOWLEDGED;
        $alert->acknowledged_at = now();
        $alert->acknowledged_by_team_member_id = $teamMemberId;
        $alert->save();

        $this->auditLogger->log('staff_sos.acknowledged', $alert, null, [
            'team_member_id' => $teamMemberId,
        ]);

        return $alert->fresh(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines']);
    }

    /**
     * Shift appointment forward and notify the client (WhatsApp-prefer).
     */
    public function shiftAppointment(
        StaffSosAlert $alert,
        int $minutes,
        ?string $teamMemberId = null,
    ): array {
        if (! in_array($minutes, self::SHIFT_MINUTES, true)) {
            throw ValidationException::withMessages([
                'minutes' => ['Shift must be 10, 20, 30, or 45 minutes.'],
            ]);
        }

        if ($alert->appointment_id === null) {
            throw ValidationException::withMessages([
                'alert' => ['This alert has no appointment to shift.'],
            ]);
        }

        $appointment = $this->appointments->find($alert->appointment_id);
        $newStarts = $appointment->starts_at->copy()->addMinutes($minutes);

        $updated = $this->appointments->update($appointment, [
            'starts_at' => $newStarts->toDateTimeString(),
        ], [
            'shift_minutes' => $minutes,
            'created_by_team_member_id' => $teamMemberId,
        ]);

        $alert = $this->acknowledge($alert, $teamMemberId);
        $alert->status = StaffSosAlert::STATUS_RESOLVED;
        $alert->save();

        return [
            'alert' => $alert->fresh(['appointment.client', 'appointment.teamMember', 'appointment.location', 'appointment.serviceLines']),
            'appointment' => $updated,
        ];
    }

    private function dispatchPush(StaffSosAlert $alert): void
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            return;
        }

        $subs = TenantOwnerPushSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->all();

        $this->push->sendToSubscriptions($subs, [
            'title' => $alert->title,
            'body' => (string) $alert->body,
            'url' => '/admin/bookings?sos='.$alert->id,
            'data' => [
                'type' => 'staff_sos',
                'sos' => true,
                'alert_id' => $alert->id,
                'kind' => $alert->kind,
                'allow_shift' => (bool) (($alert->payload_json['allow_shift'] ?? false)),
                'appointment_id' => $alert->appointment_id,
                'require_ack' => true,
            ],
        ], 'staff_sos.push');
    }
}
