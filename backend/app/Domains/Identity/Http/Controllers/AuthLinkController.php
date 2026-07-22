<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Services\StaffAuthLinkService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthLinkController extends Controller
{
    public function __construct(private readonly StaffAuthLinkService $authLinks) {}

    public function requestMagic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->authLinks->requestMagicLogin($data['email']);

        return ApiResponse::success(
            ['sent' => true],
            'If an account exists for that email, a magic link has been sent.',
        );
    }

    public function consumeMagic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $this->authLinks->consumeMagicLogin($data['token'], $data['device_name'] ?? null);
        $user = $result['user'];
        $tenant = $result['tenant'] ?? null;
        $workspaceIncomplete = $user->needsWorkspace() && $tenant === null;

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ] : null,
            'workspace_incomplete' => $workspaceIncomplete,
        ], 'Authenticated');
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->authLinks->requestPasswordReset($data['email']);

        return ApiResponse::success(
            ['sent' => true],
            'If an account exists for that email, a reset link has been sent.',
        );
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->authLinks->resetPassword($data['token'], $data['password']);

        return ApiResponse::success(null, 'Password updated. You can sign in now.');
    }
}
