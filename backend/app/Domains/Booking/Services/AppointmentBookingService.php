<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Support\BookingBoardBroadcaster;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\ProgressiveModuleAccessService;
use App\Domains\Notifications\Services\NotificationTriggerService;
use App\Shared\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly AppointmentSchedulingValidator $schedulingValidator,
        private readonly BookableServiceCatalogService $catalogService,
        private readonly AppointmentDepositService $depositService,
        private readonly AppointmentLifecycleService $lifecycleService,
        private readonly NotificationTriggerService $notificationTriggers,
        private readonly AuditLogger $auditLogger,
        private readonly ProgressiveModuleAccessService $progressiveAccess,
    ) {}

    public function list(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = Appointment::query()
            ->with(['client', 'teamMember', 'location', 'workspace', 'serviceLines'])
            ->orderBy('starts_at');

        if (! empty($filters['from'])) {
            $query->where('ends_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('starts_at', '<=', $filters['to']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['team_member_id'])) {
            $query->where('team_member_id', $filters['team_member_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['booking_source'])) {
            $query->where('booking_source', $filters['booking_source']);
        }

        if (! empty($filters['walk_in_stage'])) {
            $query->where('walk_in_stage', $filters['walk_in_stage']);
        }

        return $query->get();
    }

    public function find(string $id): Appointment
    {
        $appointment = Appointment::query()
            ->with(['client', 'teamMember', 'location', 'workspace', 'serviceLines', 'createdBy', 'recurrenceSeries'])
            ->findOrFail($id);

        $this->scope->assertTenantModel($appointment);

        return $appointment;
    }

    public function create(array $data, ?string $createdByTeamMemberId = null, array $meta = []): Appointment
    {
        $this->scope->findClient($data['client_id']);
        $resolved = $this->catalogService->resolveServiceLines($data['services']);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($resolved['total_minutes']);

        $this->schedulingValidator->validate(
            $data['team_member_id'],
            $data['location_id'],
            $data['workspace_id'] ?? null,
            $startsAt,
            $endsAt,
        );

        $deposit = $this->depositService->snapshotFromResolvedLines($resolved['lines']);

        $appointment = DB::transaction(function () use ($data, $resolved, $startsAt, $endsAt, $createdByTeamMemberId, $meta, $deposit) {
            $appointment = Appointment::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'location_id' => $data['location_id'],
                'client_id' => $data['client_id'],
                'team_member_id' => $data['team_member_id'],
                'workspace_id' => $data['workspace_id'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $data['status'] ?? Appointment::STATUS_CONFIRMED,
                'booking_source' => $data['booking_source'] ?? Appointment::SOURCE_ADMIN,
                'client_notes' => $data['client_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by_team_member_id' => $createdByTeamMemberId,
                'recurrence_series_id' => $meta['recurrence_series_id'] ?? null,
                'occurrence_index' => $meta['occurrence_index'] ?? null,
                'booking_reference' => $this->generateBookingReference(),
                'public_manage_token' => $this->generatePublicManageToken(),
                'deposit_status' => $deposit['deposit_status'],
                'deposit_required_cents' => $deposit['deposit_required_cents'],
                'deposit_rule_snapshot' => $deposit['deposit_rule_snapshot'],
                'billing_settlement_status' => \App\Shared\Commerce\Enums\BillingSettlementStatus::UNSETTLED,
            ]);

            foreach ($resolved['lines'] as $line) {
                AppointmentServiceLine::query()->create([
                    'tenant_id' => $appointment->tenant_id,
                    'appointment_id' => $appointment->id,
                    ...$line,
                ]);
            }

            $this->auditLogger->log('appointment.created', $appointment, null, [
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'team_member_id' => $appointment->team_member_id,
                'deposit_status' => $appointment->deposit_status,
            ]);

            $appointment = $appointment->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines', 'recurrenceSeries']);

            $this->notificationTriggers->safe(
                fn () => $this->notificationTriggers->sendBookingConfirmation($appointment)
            );

            if ($appointment->booking_source === Appointment::SOURCE_ONLINE) {
                $this->notificationTriggers->safe(
                    fn () => $this->notificationTriggers->sendOnlineBookingStaffAlert($appointment)
                );
            }

            try {
                $tenant = Tenant::query()->find($appointment->tenant_id);
                if ($tenant) {
                    $this->progressiveAccess->maybeNudgeAfterAppointmentCreated($tenant, $appointment);
                }
            } catch (\Throwable) {
                // Upgrade nudges must not block booking creation.
            }

            return $appointment;
        });

        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function updateStatus(Appointment $appointment, string $status, ?string $noShowReason = null): Appointment
    {
        return $this->lifecycleService->transition($appointment, $status, $noShowReason);
    }

    public function correctStatus(Appointment $appointment, string $status, string $correctionNote): Appointment
    {
        return $this->lifecycleService->correctStatus($appointment, $status, $correctionNote);
    }

    public function reassignWorkspace(Appointment $appointment, ?string $workspaceId): Appointment
    {
        $this->scope->assertTenantModel($appointment);

        if (in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'appointment' => ['Cannot reassign workspace on a terminal appointment.'],
            ]);
        }

        if ($appointment->isWalkInWaiting()) {
            throw ValidationException::withMessages([
                'appointment' => ['Seat the walk-in before assigning a workspace.'],
            ]);
        }

        $this->schedulingValidator->validate(
            $appointment->team_member_id,
            $appointment->location_id,
            $workspaceId,
            $appointment->starts_at,
            $appointment->ends_at,
            $appointment->id,
        );

        $old = ['workspace_id' => $appointment->workspace_id];
        $appointment->workspace_id = $workspaceId;
        $appointment->save();

        $this->auditLogger->log('appointment.workspace_reassigned', $appointment, $old, [
            'workspace_id' => $workspaceId,
        ]);

        $appointment = $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);
        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function updateDepositStatus(Appointment $appointment, string $status): Appointment
    {
        $this->scope->assertTenantModel($appointment);

        if (! in_array($status, Appointment::depositStatuses(), true)) {
            throw ValidationException::withMessages(['deposit_status' => ['Invalid deposit status.']]);
        }

        $old = ['deposit_status' => $appointment->deposit_status];
        $appointment->deposit_status = $status;
        $appointment->save();

        $this->auditLogger->log('appointment.deposit_status_updated', $appointment, $old, ['deposit_status' => $status]);

        return $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);
    }

    private function generateBookingReference(): string
    {
        do {
            $reference = 'NM-'.strtoupper(Str::random(8));
        } while (Appointment::withoutGlobalScopes()->where('booking_reference', $reference)->exists());

        return $reference;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $notifyContext  Extra context for reschedule client notifications
     */
    public function update(Appointment $appointment, array $data, array $notifyContext = []): Appointment
    {
        $this->scope->assertTenantModel($appointment);

        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'appointment' => ['Cancelled appointments cannot be updated.'],
            ]);
        }

        $teamMemberId = $data['team_member_id'] ?? $appointment->team_member_id;
        $locationId = $data['location_id'] ?? $appointment->location_id;
        $workspaceId = array_key_exists('workspace_id', $data)
            ? $data['workspace_id']
            : $appointment->workspace_id;

        $previousStartsAt = $appointment->starts_at?->copy();
        $previousTeamMemberId = $appointment->team_member_id;

        $startsAt = isset($data['starts_at'])
            ? Carbon::parse($data['starts_at'])
            : $appointment->starts_at->copy();

        if (isset($data['services'])) {
            $resolved = $this->catalogService->resolveServiceLines($data['services']);
            $endsAt = $startsAt->copy()->addMinutes($resolved['total_minutes']);
        } elseif (isset($data['ends_at'])) {
            $resolved = null;
            $endsAt = Carbon::parse($data['ends_at']);
        } elseif (isset($data['starts_at'])) {
            $resolved = null;
            $duration = max(1, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
            $endsAt = $startsAt->copy()->addMinutes($duration);
        } else {
            $resolved = null;
            $endsAt = $appointment->ends_at->copy();
        }

        $this->schedulingValidator->validate(
            $teamMemberId,
            $locationId,
            $workspaceId,
            $startsAt,
            $endsAt,
            $appointment->id,
        );

        $appointment = DB::transaction(function () use ($appointment, $data, $teamMemberId, $locationId, $workspaceId, $startsAt, $endsAt, $resolved) {
            $old = $appointment->only(['starts_at', 'ends_at', 'team_member_id', 'workspace_id']);

            $appointment->fill([
                'location_id' => $locationId,
                'client_id' => $data['client_id'] ?? $appointment->client_id,
                'team_member_id' => $teamMemberId,
                'workspace_id' => $workspaceId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'client_notes' => $data['client_notes'] ?? $appointment->client_notes,
                'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
            ]);
            $appointment->save();

            if ($resolved !== null) {
                $appointment->serviceLines()->delete();
                foreach ($resolved['lines'] as $line) {
                    AppointmentServiceLine::query()->create([
                        'tenant_id' => $appointment->tenant_id,
                        'appointment_id' => $appointment->id,
                        ...$line,
                    ]);
                }
            }

            $action = isset($data['starts_at']) || isset($data['team_member_id'])
                ? 'appointment.rescheduled'
                : 'appointment.updated';

            $this->auditLogger->log($action, $appointment, $old, $appointment->only([
                'starts_at', 'ends_at', 'team_member_id', 'workspace_id',
            ]));

            return $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);
        });

        $timeChanged = $previousStartsAt === null
            || ! $previousStartsAt->equalTo($appointment->starts_at);
        $providerChanged = $previousTeamMemberId !== $appointment->team_member_id;

        if ($timeChanged || $providerChanged) {
            $this->notificationTriggers->safe(
                fn () => $this->notificationTriggers->sendBookingReschedule($appointment, array_merge([
                    'previous_starts_at' => $previousStartsAt?->toIso8601String(),
                ], $notifyContext))
            );
        }

        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        $this->scope->assertTenantModel($appointment);

        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            return $appointment;
        }

        $old = ['status' => $appointment->status];
        $appointment->status = Appointment::STATUS_CANCELLED;
        $appointment->cancelled_at = now();
        $appointment->cancellation_reason = $reason;
        $appointment->save();

        $this->auditLogger->log('appointment.cancelled', $appointment, $old, [
            'cancellation_reason' => $reason,
        ]);

        $appointment = $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);

        $this->notificationTriggers->safe(
            fn () => $this->notificationTriggers->sendBookingCancellation($appointment)
        );

        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    private function generatePublicManageToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (Appointment::withoutGlobalScopes()->where('public_manage_token', $token)->exists());

        return $token;
    }
}
