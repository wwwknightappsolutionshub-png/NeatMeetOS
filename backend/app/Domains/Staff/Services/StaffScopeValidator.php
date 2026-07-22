<?php

namespace App\Domains\Staff\Services;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class StaffScopeValidator
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function findTeamMember(string $id): TeamMember
    {
        return TeamMember::query()->findOrFail($id);
    }

    public function assertTeamMember(TeamMember $teamMember): void
    {
        if ($teamMember->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['team_member' => ['Team member not found.']]);
        }
    }

    public function validateLocation(?string $locationId, string $tenantId): void
    {
        if ($locationId === null) {
            return;
        }

        if (! Location::query()->where('id', $locationId)->where('tenant_id', $tenantId)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => ['Location does not belong to this tenant.'],
            ]);
        }
    }

    public function validateWorkspace(?string $workspaceId, string $tenantId): void
    {
        if ($workspaceId === null) {
            return;
        }

        if (! Workspace::query()->where('id', $workspaceId)->where('tenant_id', $tenantId)->exists()) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace does not belong to this tenant.'],
            ]);
        }
    }

    public function validateLocationIds(array $locationIds, string $tenantId): void
    {
        if ($locationIds === []) {
            return;
        }

        $count = Location::query()
            ->whereIn('id', $locationIds)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($count !== count($locationIds)) {
            throw ValidationException::withMessages([
                'location_ids' => ['One or more locations are invalid for this tenant.'],
            ]);
        }
    }

    public function validateWorkspaceIds(array $workspaceIds, string $tenantId): void
    {
        if ($workspaceIds === []) {
            return;
        }

        $count = Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($count !== count($workspaceIds)) {
            throw ValidationException::withMessages([
                'workspace_ids' => ['One or more workspaces are invalid for this tenant.'],
            ]);
        }
    }
}
