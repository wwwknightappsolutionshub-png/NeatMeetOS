<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Staff\Http\Resources\StaffAvailabilityRuleResource;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Services\StaffAvailabilityService;
use App\Domains\Staff\Services\StaffScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAvailabilityController extends Controller
{
    public function __construct(
        private readonly StaffAvailabilityService $availabilityService,
        private readonly StaffScopeValidator $scope,
    ) {}

    public function index(string $teamMemberId): JsonResponse
    {
        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $this->scope->assertTeamMember($teamMember);

        $rules = $this->availabilityService->listForProvider($teamMember);

        return ApiResponse::success(StaffAvailabilityRuleResource::collection($rules));
    }

    public function store(Request $request, string $teamMemberId): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(StaffAvailabilityRule::daysOfWeek()))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $rule = $this->availabilityService->create($teamMember, $data);

        return ApiResponse::success(new StaffAvailabilityRuleResource($rule), 'Availability created', 201);
    }

    public function update(Request $request, string $teamMemberId, string $id): JsonResponse
    {
        $rule = $this->availabilityService->find($id);
        abort_if($rule->team_member_id !== $teamMemberId, 404);

        $data = $request->validate([
            'location_id' => ['sometimes', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'day_of_week' => ['sometimes', 'integer', Rule::in(array_keys(StaffAvailabilityRule::daysOfWeek()))],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $rule = $this->availabilityService->update($rule, $data);

        return ApiResponse::success(new StaffAvailabilityRuleResource($rule), 'Availability updated');
    }

    public function archive(string $teamMemberId, string $id): JsonResponse
    {
        $rule = $this->availabilityService->find($id);
        abort_if($rule->team_member_id !== $teamMemberId, 404);

        $rule = $this->availabilityService->archive($rule);

        return ApiResponse::success(new StaffAvailabilityRuleResource($rule), 'Availability archived');
    }
}
