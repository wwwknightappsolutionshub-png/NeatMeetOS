<?php

namespace App\Domains\Staff\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Staff\Models\StaffAbsence;
use App\Shared\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class StaffAbsenceService
{
    public function __construct(
        private readonly StaffScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForProvider(TeamMember $teamMember, bool $activeOnly = true): \Illuminate\Database\Eloquent\Collection
    {
        $this->scope->assertTeamMember($teamMember);

        $query = StaffAbsence::query()
            ->where('team_member_id', $teamMember->id)
            ->orderByDesc('starts_at');

        if ($activeOnly) {
            $query->where('status', StaffAbsence::STATUS_ACTIVE);
        }

        return $query->get();
    }

    public function find(string $id): StaffAbsence
    {
        return StaffAbsence::query()->findOrFail($id);
    }

    public function create(TeamMember $teamMember, array $data): StaffAbsence
    {
        $this->scope->assertTeamMember($teamMember);
        $this->validateDateRange($data['starts_at'], $data['ends_at']);

        $absence = StaffAbsence::query()->create([
            'tenant_id' => $teamMember->tenant_id,
            'team_member_id' => $teamMember->id,
            'category' => $data['category'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'note' => $data['note'] ?? null,
            'status' => StaffAbsence::STATUS_ACTIVE,
        ]);

        $this->auditLogger->log('staff.absence_created', $absence, null, $absence->toArray());

        return $absence;
    }

    public function update(StaffAbsence $absence, array $data): StaffAbsence
    {
        $this->assertTenantAbsence($absence);

        if ($absence->status === StaffAbsence::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'absence' => ['Cancelled absences cannot be updated.'],
            ]);
        }

        $starts = $data['starts_at'] ?? $absence->starts_at->toDateTimeString();
        $ends = $data['ends_at'] ?? $absence->ends_at->toDateTimeString();
        $this->validateDateRange($starts, $ends);

        $old = $absence->toArray();
        $absence->fill(collect($data)->only([
            'category',
            'starts_at',
            'ends_at',
            'note',
        ])->all());
        $absence->save();

        $this->auditLogger->log('staff.absence_updated', $absence, $old, $absence->toArray());

        return $absence->fresh();
    }

    public function cancel(StaffAbsence $absence): StaffAbsence
    {
        $this->assertTenantAbsence($absence);

        if ($absence->status === StaffAbsence::STATUS_CANCELLED) {
            return $absence;
        }

        $old = ['status' => $absence->status];
        $absence->status = StaffAbsence::STATUS_CANCELLED;
        $absence->save();

        $this->auditLogger->log('staff.absence_cancelled', $absence, $old, ['status' => StaffAbsence::STATUS_CANCELLED]);

        return $absence->fresh();
    }

    private function assertTenantAbsence(StaffAbsence $absence): void
    {
        if ($absence->tenant_id !== app(\App\Shared\Tenancy\TenantContext::class)->id()) {
            throw ValidationException::withMessages(['absence' => ['Absence not found.']]);
        }
    }

    private function validateDateRange(string $startsAt, string $endsAt): void
    {
        if ($startsAt >= $endsAt) {
            throw ValidationException::withMessages([
                'ends_at' => ['End must be after start.'],
            ]);
        }
    }
}
