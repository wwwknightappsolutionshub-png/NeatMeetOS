<?php

namespace App\Domains\Staff\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Shared\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class StaffAvailabilityService
{
    public function __construct(
        private readonly StaffScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly StaffProfileService $profiles,
    ) {}

    public function listForProvider(TeamMember $teamMember): \Illuminate\Database\Eloquent\Collection
    {
        $this->scope->assertTeamMember($teamMember);

        return StaffAvailabilityRule::query()
            ->with(['location', 'workspace'])
            ->where('team_member_id', $teamMember->id)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function find(string $id): StaffAvailabilityRule
    {
        $rule = StaffAvailabilityRule::query()
            ->with(['location', 'workspace', 'teamMember'])
            ->findOrFail($id);

        if ($rule->tenant_id !== app(\App\Shared\Tenancy\TenantContext::class)->id()) {
            throw ValidationException::withMessages(['availability' => ['Availability rule not found.']]);
        }

        return $rule;
    }

    public function create(TeamMember $teamMember, array $data): StaffAvailabilityRule
    {
        $this->scope->assertTeamMember($teamMember);
        $this->scope->validateLocation($data['location_id'], $teamMember->tenant_id);
        $this->scope->validateWorkspace($data['workspace_id'] ?? null, $teamMember->tenant_id);
        $this->validateTimeWindow($data['start_time'], $data['end_time']);

        $rule = StaffAvailabilityRule::query()->create([
            'tenant_id' => $teamMember->tenant_id,
            'team_member_id' => $teamMember->id,
            'location_id' => $data['location_id'],
            'workspace_id' => $data['workspace_id'] ?? null,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_active' => true,
        ]);

        // Online slot search requires is_bookable — enable when hours are published.
        $this->profiles->ensureOnlineBookable($teamMember);

        $this->auditLogger->log('staff.availability_created', $rule, null, $rule->toArray());

        return $rule->load(['location', 'workspace']);
    }

    public function update(StaffAvailabilityRule $rule, array $data): StaffAvailabilityRule
    {
        $this->assertTenantRule($rule);

        if (array_key_exists('location_id', $data)) {
            $this->scope->validateLocation($data['location_id'], $rule->tenant_id);
        }

        if (array_key_exists('workspace_id', $data)) {
            $this->scope->validateWorkspace($data['workspace_id'], $rule->tenant_id);
        }

        $start = $data['start_time'] ?? $rule->start_time;
        $end = $data['end_time'] ?? $rule->end_time;
        $this->validateTimeWindow($start, $end);

        $old = $rule->toArray();
        $rule->fill(collect($data)->only([
            'location_id',
            'workspace_id',
            'day_of_week',
            'start_time',
            'end_time',
        ])->all());
        $rule->save();

        $this->auditLogger->log('staff.availability_updated', $rule, $old, $rule->toArray());

        return $rule->fresh(['location', 'workspace']);
    }

    public function archive(StaffAvailabilityRule $rule): StaffAvailabilityRule
    {
        $this->assertTenantRule($rule);

        $rule->is_active = false;
        $rule->save();

        $this->auditLogger->log('staff.availability_archived', $rule);

        return $rule->fresh();
    }

    private function assertTenantRule(StaffAvailabilityRule $rule): void
    {
        if ($rule->tenant_id !== app(\App\Shared\Tenancy\TenantContext::class)->id()) {
            throw ValidationException::withMessages(['availability' => ['Availability rule not found.']]);
        }
    }

    private function validateTimeWindow(string $start, string $end): void
    {
        if ($start >= $end) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after start time.'],
            ]);
        }
    }
}
