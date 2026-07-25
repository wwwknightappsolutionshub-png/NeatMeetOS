<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\PlatformStaffService;
use App\Domains\Identity\Support\PlatformRole;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PlatformStaffController extends Controller
{
    public function __construct(
        private readonly PlatformStaffService $staff,
    ) {}

    public function index(): JsonResponse
    {
        $items = $this->staff->list()
            ->map(fn (User $user) => $this->staff->serialize($user))
            ->values()
            ->all();

        return ApiResponse::success([
            'items' => $items,
            'roles' => collect(PlatformRole::all())
                ->map(fn (string $role) => [
                    'slug' => $role,
                    'label' => PlatformRole::label($role),
                    'description' => PlatformRole::description($role),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
            'platform_role' => ['required', Rule::in([PlatformRole::MANAGER, PlatformRole::SUPPORT])],
        ]);

        $user = $this->staff->create($actor, $data);

        return ApiResponse::success($this->staff->serialize($user), 'Platform staff created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'platform_role' => ['sometimes', Rule::in(PlatformRole::all())],
        ]);

        $user = $this->staff->update($actor, $target, $data);

        return ApiResponse::success($this->staff->serialize($user), 'Platform staff updated');
    }

    public function updatePassword(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($id);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->staff->setPassword($actor, $target, $data['password']);

        return ApiResponse::success(null, 'Staff password updated');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($id);
        $user = $this->staff->revoke($actor, $target);

        return ApiResponse::success($this->staff->serialize($user), 'Platform access revoked');
    }
}
