<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Staff\Models\StaffAbsence;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentSchedulingValidator
{
    public function __construct(private readonly BookingScopeValidator $scope) {}

    public function validate(
        string $teamMemberId,
        string $locationId,
        ?string $workspaceId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?string $excludeAppointmentId = null,
    ): void {
        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['Appointment end must be after start.'],
            ]);
        }

        $provider = $this->scope->findTeamMember($teamMemberId);
        $location = $this->scope->findLocation($locationId);
        $workspace = $this->scope->findWorkspace($workspaceId);

        $this->assertProviderActiveAndBookable($provider);
        $this->assertProviderOperatingScope($provider, $locationId, $workspace);
        $this->assertWorkspaceLocation($workspace, $locationId);
        $this->assertWithinLocationOpeningHours($location, $startsAt, $endsAt);
        $this->assertFitsAvailability($provider, $locationId, $workspaceId, $startsAt, $endsAt);
        $this->assertNoAbsence($provider, $startsAt, $endsAt);
        $this->assertNoProviderConflict($teamMemberId, $startsAt, $endsAt, $excludeAppointmentId);

        if ($workspaceId !== null) {
            $this->assertNoWorkspaceConflict($workspaceId, $startsAt, $endsAt, $excludeAppointmentId);
        }
    }

    private function assertProviderActiveAndBookable(TeamMember $provider): void
    {
        if (! $provider->is_active) {
            throw ValidationException::withMessages([
                'team_member_id' => ['Provider is not active.'],
            ]);
        }

        $profile = StaffProfile::query()->where('team_member_id', $provider->id)->first();

        if ($profile === null || ! $profile->is_bookable) {
            throw ValidationException::withMessages([
                'team_member_id' => ['Provider is not bookable.'],
            ]);
        }
    }

    private function assertProviderOperatingScope(
        TeamMember $provider,
        string $locationId,
        ?Workspace $workspace,
    ): void {
        $operatingLocationIds = $provider->operatingLocations()->pluck('locations.id')->all();
        $hasLocationScope = $operatingLocationIds === []
            || in_array($locationId, $operatingLocationIds, true)
            || $provider->primary_location_id === $locationId;

        if (! $hasLocationScope) {
            throw ValidationException::withMessages([
                'location_id' => ['Provider does not operate at this location.'],
            ]);
        }

        if ($workspace === null) {
            return;
        }

        $workspaceIds = $provider->workspaces()->pluck('workspaces.id')->all();

        if ($workspaceIds !== [] && ! in_array($workspace->id, $workspaceIds, true)) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace is not in provider operating scope.'],
            ]);
        }
    }

    private function assertWorkspaceLocation(?Workspace $workspace, string $locationId): void
    {
        if ($workspace === null) {
            return;
        }

        if ($workspace->location_id !== $locationId) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace does not belong to the appointment location.'],
            ]);
        }
    }

    private function assertWithinLocationOpeningHours(
        Location $location,
        Carbon $startsAt,
        Carbon $endsAt,
    ): void {
        if (! $location->isOpenForInterval($startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Appointment is outside salon opening hours.'],
            ]);
        }
    }

    private function assertFitsAvailability(
        TeamMember $provider,
        string $locationId,
        ?string $workspaceId,
        Carbon $startsAt,
        Carbon $endsAt,
    ): void {
        $dayOfWeek = $startsAt->dayOfWeekIso;
        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');

        $rules = StaffAvailabilityRule::query()
            ->where('team_member_id', $provider->id)
            ->where('location_id', $locationId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $fits = $rules->contains(function (StaffAvailabilityRule $rule) use ($startTime, $endTime, $workspaceId) {
            if ($rule->workspace_id !== null && $workspaceId !== null && $rule->workspace_id !== $workspaceId) {
                return false;
            }

            return $rule->start_time <= $startTime && $rule->end_time >= $endTime;
        });

        if (! $fits) {
            throw ValidationException::withMessages([
                'starts_at' => ['Appointment is outside provider availability.'],
            ]);
        }
    }

    private function assertNoAbsence(TeamMember $provider, Carbon $startsAt, Carbon $endsAt): void
    {
        $overlap = StaffAbsence::query()
            ->where('team_member_id', $provider->id)
            ->where('status', StaffAbsence::STATUS_ACTIVE)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => ['Provider is absent during this time.'],
            ]);
        }
    }

    private function assertNoProviderConflict(
        string $teamMemberId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?string $excludeAppointmentId,
    ): void {
        $query = Appointment::query()
            ->where('team_member_id', $teamMemberId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->where(function ($q) {
                $q->whereNull('walk_in_stage')
                    ->orWhere('walk_in_stage', '!=', Appointment::WALK_IN_WAITING);
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => ['Provider already has an overlapping appointment.'],
            ]);
        }
    }

    private function assertNoWorkspaceConflict(
        string $workspaceId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?string $excludeAppointmentId,
    ): void {
        $query = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->where(function ($q) {
                $q->whereNull('walk_in_stage')
                    ->orWhere('walk_in_stage', '!=', Appointment::WALK_IN_WAITING);
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace is already booked for this time.'],
            ]);
        }
    }
}
