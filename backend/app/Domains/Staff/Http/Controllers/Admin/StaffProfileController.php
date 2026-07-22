<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Staff\Http\Resources\StaffProfileResource;
use App\Domains\Staff\Http\Resources\StaffProviderResource;
use App\Domains\Staff\Services\StaffProfileService;
use App\Domains\Staff\Services\StaffScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffProfileController extends Controller
{
    public function __construct(
        private readonly StaffProfileService $profileService,
        private readonly StaffScopeValidator $scope,
    ) {}

    public function update(Request $request, string $teamMemberId): JsonResponse
    {
        $data = $request->validate([
            'is_bookable' => ['sometimes', 'boolean'],
            'show_in_online_booking' => ['sometimes', 'boolean'],
            'accepts_walk_ins' => ['sometimes', 'boolean'],
            'booking_display_name' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'default_workspace_id' => ['nullable', 'uuid'],
            'min_lead_time_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        ]);

        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $profile = $this->profileService->update($teamMember, $data);

        return ApiResponse::success(new StaffProfileResource($profile), 'Provider profile updated');
    }

    public function updateOperatingScope(Request $request, string $teamMemberId): JsonResponse
    {
        $data = $request->validate([
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['uuid'],
            'workspace_ids' => ['nullable', 'array'],
            'workspace_ids.*' => ['uuid'],
        ]);

        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $teamMember = $this->profileService->syncOperatingScope($teamMember, $data);

        return ApiResponse::success(
            new StaffProviderResource($teamMember->load(['operatingLocations', 'workspaces', 'staffProfile'])),
            'Operating scope updated',
        );
    }
}
