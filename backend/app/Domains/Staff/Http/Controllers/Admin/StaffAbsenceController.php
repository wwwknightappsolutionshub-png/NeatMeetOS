<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Staff\Http\Resources\StaffAbsenceResource;
use App\Domains\Staff\Models\StaffAbsence;
use App\Domains\Staff\Services\StaffAbsenceService;
use App\Domains\Staff\Services\StaffScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAbsenceController extends Controller
{
    public function __construct(
        private readonly StaffAbsenceService $absenceService,
        private readonly StaffScopeValidator $scope,
    ) {}

    public function index(Request $request, string $teamMemberId): JsonResponse
    {
        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $this->scope->assertTeamMember($teamMember);

        $activeOnly = $request->boolean('active_only', true);
        $absences = $this->absenceService->listForProvider($teamMember, $activeOnly);

        return ApiResponse::success(StaffAbsenceResource::collection($absences));
    }

    public function store(Request $request, string $teamMemberId): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(StaffAbsence::categories())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $teamMember = $this->scope->findTeamMember($teamMemberId);
        $absence = $this->absenceService->create($teamMember, $data);

        return ApiResponse::success(new StaffAbsenceResource($absence), 'Absence created', 201);
    }

    public function update(Request $request, string $teamMemberId, string $id): JsonResponse
    {
        $absence = $this->absenceService->find($id);
        abort_if($absence->team_member_id !== $teamMemberId, 404);

        $data = $request->validate([
            'category' => ['sometimes', Rule::in(StaffAbsence::categories())],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $absence = $this->absenceService->update($absence, $data);

        return ApiResponse::success(new StaffAbsenceResource($absence), 'Absence updated');
    }

    public function cancel(string $teamMemberId, string $id): JsonResponse
    {
        $absence = $this->absenceService->find($id);
        abort_if($absence->team_member_id !== $teamMemberId, 404);

        $absence = $this->absenceService->cancel($absence);

        return ApiResponse::success(new StaffAbsenceResource($absence), 'Absence cancelled');
    }
}
