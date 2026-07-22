<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\WorkspaceResource;
use App\Domains\Identity\Models\Workspace;
use App\Domains\Identity\Services\WorkspaceService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function index(Request $request): JsonResponse
    {
        $locationId = $request->query('location_id');

        return ApiResponse::success(
            WorkspaceResource::collection($this->workspaceService->list($locationId)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'workspace_type' => ['required', Rule::in([
                Workspace::TYPE_CHAIR,
                Workspace::TYPE_ROOM,
                Workspace::TYPE_STATION,
                Workspace::TYPE_SEAT,
                Workspace::TYPE_SLOT,
            ])],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $workspace = $this->workspaceService->create($data);

        return ApiResponse::success(new WorkspaceResource($workspace), 'Workspace created', 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new WorkspaceResource($this->workspaceService->find($id)));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['sometimes', 'uuid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'workspace_type' => ['sometimes', Rule::in([
                Workspace::TYPE_CHAIR,
                Workspace::TYPE_ROOM,
                Workspace::TYPE_STATION,
                Workspace::TYPE_SEAT,
                Workspace::TYPE_SLOT,
            ])],
            'metadata' => ['nullable', 'array'],
        ]);

        $workspace = $this->workspaceService->update($this->workspaceService->find($id), $data);

        return ApiResponse::success(new WorkspaceResource($workspace), 'Workspace updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $workspace = $this->workspaceService->setActive(
            $this->workspaceService->find($id),
            $data['is_active'],
        );

        return ApiResponse::success(new WorkspaceResource($workspace), 'Workspace status updated');
    }
}
