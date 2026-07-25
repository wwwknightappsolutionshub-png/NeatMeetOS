<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\PlatformStaffService;
use App\Domains\Identity\Support\PlatformRole;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PlatformProfileController extends Controller
{
    public function __construct(
        private readonly PlatformStaffService $staff,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'user' => $this->staff->serialize($user),
            'roles' => $this->roleCatalogue(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        $updated = $this->staff->updateProfile($user, $data);

        return ApiResponse::success([
            'user' => $this->staff->serialize($updated),
        ], 'Profile updated');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->staff->changePassword($user, $data['current_password'], $data['password']);

        return ApiResponse::success(null, 'Password updated');
    }

    /**
     * @return list<array{slug: string, label: string, description: string}>
     */
    private function roleCatalogue(): array
    {
        return collect(PlatformRole::all())
            ->map(fn (string $role) => [
                'slug' => $role,
                'label' => PlatformRole::label($role),
                'description' => PlatformRole::description($role),
            ])
            ->values()
            ->all();
    }
}
