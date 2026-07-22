<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\PermissionResource;
use App\Domains\Identity\Http\Resources\RoleResource;
use App\Domains\Identity\Http\Resources\TeamMemberResource;
use App\Domains\Identity\Services\PermissionCatalogueService;
use App\Domains\Identity\Services\RoleService;
use App\Domains\Identity\Services\TeamMemberService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly TeamMemberService $teamMemberService,
        private readonly PermissionCatalogueService $permissionCatalogueService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(RoleResource::collection($this->roleService->listForTenant()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new RoleResource($this->roleService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string'],
        ]);

        $role = $this->roleService->create($data);

        return ApiResponse::success(new RoleResource($role), 'Role created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', 'alpha_dash'],
        ]);

        $role = $this->roleService->update($this->roleService->find($id), $data);

        return ApiResponse::success(new RoleResource($role), 'Role updated');
    }

    public function archive(string $id): JsonResponse
    {
        $role = $this->roleService->archive($this->roleService->find($id));

        return ApiResponse::success(new RoleResource($role), 'Role archived');
    }

    public function updatePermissions(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['string'],
        ]);

        $role = $this->roleService->syncPermissions(
            $this->roleService->find($id),
            $data['permission_ids'],
        );

        return ApiResponse::success(new RoleResource($role), 'Role permissions updated');
    }

    public function permissions(): JsonResponse
    {
        $grouped = $this->permissionCatalogueService->listGrouped();

        $data = $grouped->map(fn ($permissions, $module) => [
            'module' => $module,
            'permissions' => PermissionResource::collection($permissions),
        ])->values();

        return ApiResponse::success($data);
    }

    public function updateTeamMemberRoles(Request $request, string $teamMemberId): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['uuid'],
        ]);

        $teamMember = $this->teamMemberService->find($teamMemberId);
        $teamMember = $this->teamMemberService->syncRoles($teamMember, $data['role_ids']);

        return ApiResponse::success(new TeamMemberResource($teamMember->load('roles')), 'Roles updated');
    }
}
