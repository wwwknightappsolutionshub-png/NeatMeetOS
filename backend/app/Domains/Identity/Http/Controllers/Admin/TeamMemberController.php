<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\TeamMemberResource;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Services\TeamMemberService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamMemberController extends Controller
{
    public function __construct(private readonly TeamMemberService $teamMemberService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(TeamMemberResource::collection($this->teamMemberService->list()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['required', Rule::in(TeamMember::employmentTypes())],
            'primary_location_id' => ['nullable', 'uuid'],
            'workspace_ids' => ['nullable', 'array'],
            'workspace_ids.*' => ['uuid'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['uuid'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $teamMember = $this->teamMemberService->create($data);

        return ApiResponse::success(new TeamMemberResource($teamMember), 'Team member created', 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new TeamMemberResource($this->teamMemberService->find($id)));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['sometimes', Rule::in(TeamMember::employmentTypes())],
            'primary_location_id' => ['nullable', 'uuid'],
            'workspace_ids' => ['nullable', 'array'],
            'workspace_ids.*' => ['uuid'],
        ]);

        $teamMember = $this->teamMemberService->update($this->teamMemberService->find($id), $data);

        return ApiResponse::success(new TeamMemberResource($teamMember), 'Team member updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $teamMember = $this->teamMemberService->setActive(
            $this->teamMemberService->find($id),
            $data['is_active'],
        );

        return ApiResponse::success(new TeamMemberResource($teamMember), 'Team member status updated');
    }
}
