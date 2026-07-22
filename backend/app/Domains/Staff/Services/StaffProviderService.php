<?php

namespace App\Domains\Staff\Services;

use App\Domains\Identity\Models\TeamMember;

class StaffProviderService
{
    public function __construct(
        private readonly StaffScopeValidator $scope,
        private readonly StaffProfileService $profileService,
    ) {}

    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return TeamMember::query()
            ->with(['user', 'primaryLocation', 'staffProfile', 'operatingLocations', 'workspaces'])
            ->orderBy('display_name')
            ->get();
    }

    public function show(string $teamMemberId): TeamMember
    {
        $teamMember = TeamMember::query()
            ->with([
                'user',
                'primaryLocation',
                'staffProfile.defaultWorkspace',
                'operatingLocations',
                'workspaces.location',
            ])
            ->findOrFail($teamMemberId);

        $this->scope->assertTeamMember($teamMember);
        $this->profileService->getOrCreate($teamMember);

        return $teamMember->fresh()->load([
            'user',
            'primaryLocation',
            'staffProfile.defaultWorkspace',
            'operatingLocations',
            'workspaces.location',
        ]);
    }
}
