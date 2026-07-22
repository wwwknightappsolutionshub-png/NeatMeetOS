<?php

namespace App\Domains\Staff\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class StaffProfileService
{
    public function __construct(
        private readonly StaffScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getOrCreate(TeamMember $teamMember): StaffProfile
    {
        $this->scope->assertTeamMember($teamMember);

        return StaffProfile::query()->firstOrCreate(
            ['team_member_id' => $teamMember->id],
            [
                'tenant_id' => $teamMember->tenant_id,
                'is_bookable' => false,
                'show_in_online_booking' => false,
                'accepts_walk_ins' => false,
            ],
        );
    }

    public function update(TeamMember $teamMember, array $data): StaffProfile
    {
        $this->scope->assertTeamMember($teamMember);

        if (array_key_exists('default_workspace_id', $data)) {
            $this->scope->validateWorkspace($data['default_workspace_id'], $teamMember->tenant_id);
        }

        $profile = $this->getOrCreate($teamMember);
        $old = $profile->toArray();

        $profile->fill(collect($data)->only([
            'is_bookable',
            'show_in_online_booking',
            'accepts_walk_ins',
            'booking_display_name',
            'internal_notes',
            'default_workspace_id',
            'min_lead_time_minutes',
            'buffer_minutes',
        ])->all());

        $profile->save();

        $this->auditLogger->log('staff.profile_updated', $profile, $old, $profile->toArray());

        return $profile->fresh(['defaultWorkspace']);
    }

    public function syncOperatingScope(TeamMember $teamMember, array $data): TeamMember
    {
        $this->scope->assertTeamMember($teamMember);

        return DB::transaction(function () use ($teamMember, $data) {
            $oldLocations = $teamMember->operatingLocations()->pluck('locations.id')->all();
            $oldWorkspaces = $teamMember->workspaces()->pluck('workspaces.id')->all();

            if (array_key_exists('location_ids', $data)) {
                $locationIds = $data['location_ids'] ?? [];
                $this->scope->validateLocationIds($locationIds, $teamMember->tenant_id);
                $teamMember->operatingLocations()->sync($locationIds);
            }

            if (array_key_exists('workspace_ids', $data)) {
                $workspaceIds = $data['workspace_ids'] ?? [];
                $this->scope->validateWorkspaceIds($workspaceIds, $teamMember->tenant_id);
                $teamMember->workspaces()->sync($workspaceIds);
            }

            $this->auditLogger->log(
                'staff.operating_scope_updated',
                $teamMember,
                [
                    'location_ids' => $oldLocations,
                    'workspace_ids' => $oldWorkspaces,
                ],
                [
                    'location_ids' => $teamMember->operatingLocations()->pluck('locations.id')->all(),
                    'workspace_ids' => $teamMember->workspaces()->pluck('workspaces.id')->all(),
                ],
            );

            return $teamMember->fresh()->load(['operatingLocations', 'workspaces']);
        });
    }
}
