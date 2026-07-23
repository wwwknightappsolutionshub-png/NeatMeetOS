<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $member = TeamMember::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('tenant')
            ->orderByDesc('id')
            ->first();

        $tenant = $member?->tenant;
        if (
            ! $user->is_platform_admin
            && $tenant !== null
            && $tenant->status === 'pending_activation'
        ) {
            throw ValidationException::withMessages([
                'email' => ['Activate your account via the confirmation email before signing in.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'neatmeet-os-web')->plainTextToken;

        $workspaceIncomplete = $user->needsWorkspace() && $tenant === null;

        return ApiResponse::success([
            'token' => $token,
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = null;
        if ($user !== null) {
            $member = TeamMember::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->with('tenant')
                ->orderByDesc('id')
                ->first();
        }
        $tenant = $member?->tenant;

        return ApiResponse::success([
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ] : null,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ] : null,
            'workspace_incomplete' => $user
                ? ($user->needsWorkspace() && $tenant === null)
                : false,
        ]);
    }
}
