<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Booking\Support\BookingBoardBroadcaster;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\ProgressiveModuleAccessService;
use App\Shared\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalkInService
{
    public function __construct(
        private readonly BookingScopeValidator $scope,
        private readonly BookableServiceCatalogService $catalogService,
        private readonly AppointmentSchedulingValidator $schedulingValidator,
        private readonly AppointmentDepositService $depositService,
        private readonly AuditLogger $auditLogger,
        private readonly ProgressiveModuleAccessService $progressiveAccess,
    ) {}

    public function list(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Appointment::query()
            ->where('booking_source', Appointment::SOURCE_WALK_IN)
            ->with(['client', 'teamMember', 'location', 'workspace', 'serviceLines'])
            ->orderBy('arrived_at');

        if (! empty($filters['walk_in_stage'])) {
            $query->where('walk_in_stage', $filters['walk_in_stage']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (empty($filters['include_completed'])) {
            $query->whereIn('walk_in_stage', [
                Appointment::WALK_IN_WAITING,
                Appointment::WALK_IN_SEATED,
            ]);
        }

        return $query->get();
    }

    public function create(array $data, ?string $createdByTeamMemberId = null): Appointment
    {
        $this->scope->findClient($data['client_id']);
        $this->scope->findLocation($data['location_id']);
        $resolved = $this->catalogService->resolveServiceLines($data['services']);

        $arrivedAt = Carbon::now();
        $hasProvider = ! empty($data['team_member_id']);
        $seatImmediately = ($data['seat_immediately'] ?? $hasProvider) && $hasProvider;
        $teamMemberId = $data['team_member_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;

        $startsAt = $arrivedAt->copy();
        $endsAt = $startsAt->copy()->addMinutes($resolved['total_minutes']);

        if ($seatImmediately) {
            $this->schedulingValidator->validate(
                $teamMemberId,
                $data['location_id'],
                $workspaceId,
                $startsAt,
                $endsAt,
            );
        }

        $deposit = $this->depositService->snapshotFromResolvedLines($resolved['lines']);
        $walkInStage = $seatImmediately ? Appointment::WALK_IN_SEATED : Appointment::WALK_IN_WAITING;
        $status = $seatImmediately ? Appointment::STATUS_CHECKED_IN : Appointment::STATUS_PENDING;

        $appointment = DB::transaction(function () use (
            $data,
            $resolved,
            $arrivedAt,
            $startsAt,
            $endsAt,
            $teamMemberId,
            $workspaceId,
            $createdByTeamMemberId,
            $deposit,
            $walkInStage,
            $status,
        ) {
            $appointment = Appointment::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'location_id' => $data['location_id'],
                'client_id' => $data['client_id'],
                'team_member_id' => $teamMemberId,
                'workspace_id' => $workspaceId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $status,
                'booking_source' => Appointment::SOURCE_WALK_IN,
                'walk_in_stage' => $walkInStage,
                'arrived_at' => $arrivedAt,
                'client_notes' => $data['client_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by_team_member_id' => $createdByTeamMemberId,
                'booking_reference' => $this->generateBookingReference(),
                'deposit_status' => $deposit['deposit_status'],
                'deposit_required_cents' => $deposit['deposit_required_cents'],
                'deposit_rule_snapshot' => $deposit['deposit_rule_snapshot'],
            ]);

            foreach ($resolved['lines'] as $line) {
                AppointmentServiceLine::query()->create([
                    'tenant_id' => $appointment->tenant_id,
                    'appointment_id' => $appointment->id,
                    ...$line,
                ]);
            }

            $this->auditLogger->log('walk_in.created', $appointment, null, [
                'walk_in_stage' => $walkInStage,
                'arrived_at' => $arrivedAt->toIso8601String(),
            ]);

            $appointment = $appointment->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);

            try {
                $tenant = Tenant::query()->find($appointment->tenant_id);
                if ($tenant) {
                    $this->progressiveAccess->maybeNudgeAfterAppointmentCreated($tenant, $appointment);
                }
            } catch (\Throwable) {
                // Upgrade nudges must not block walk-in creation.
            }

            return $appointment;
        });

        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    public function seat(Appointment $appointment, array $data): Appointment
    {
        $this->scope->assertTenantModel($appointment);

        if ($appointment->booking_source !== Appointment::SOURCE_WALK_IN) {
            throw ValidationException::withMessages([
                'appointment' => ['Only walk-in appointments can be seated.'],
            ]);
        }

        if ($appointment->walk_in_stage !== Appointment::WALK_IN_WAITING) {
            throw ValidationException::withMessages([
                'walk_in_stage' => ['Walk-in is not in waiting state.'],
            ]);
        }

        if (empty($data['team_member_id'])) {
            throw ValidationException::withMessages([
                'team_member_id' => ['Provider is required to seat a walk-in.'],
            ]);
        }

        $teamMemberId = $data['team_member_id'];
        $workspaceId = $data['workspace_id'] ?? $appointment->workspace_id;
        $startsAt = isset($data['starts_at'])
            ? Carbon::parse($data['starts_at'])
            : Carbon::now();

        $appointment->loadMissing('serviceLines');

        $durationMinutes = $appointment->serviceLines->sum('duration_minutes') ?: 60;
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        $this->schedulingValidator->validate(
            $teamMemberId,
            $appointment->location_id,
            $workspaceId,
            $startsAt,
            $endsAt,
            $appointment->id,
        );

        $old = $appointment->only(['team_member_id', 'workspace_id', 'walk_in_stage', 'status', 'starts_at', 'ends_at']);

        $appointment->team_member_id = $teamMemberId;
        $appointment->workspace_id = $workspaceId;
        $appointment->starts_at = $startsAt;
        $appointment->ends_at = $endsAt;
        $appointment->walk_in_stage = Appointment::WALK_IN_SEATED;
        $appointment->status = Appointment::STATUS_CHECKED_IN;
        $appointment->save();

        $this->auditLogger->log('walk_in.seated', $appointment, $old, $appointment->only([
            'team_member_id', 'workspace_id', 'walk_in_stage', 'status', 'starts_at', 'ends_at',
        ]));

        $appointment = $appointment->fresh()->load(['client', 'teamMember', 'location', 'workspace', 'serviceLines']);
        BookingBoardBroadcaster::forAppointment($appointment);

        return $appointment;
    }

    private function generateBookingReference(): string
    {
        do {
            $reference = 'NM-'.strtoupper(Str::random(8));
        } while (Appointment::withoutGlobalScopes()->where('booking_reference', $reference)->exists());

        return $reference;
    }
}
