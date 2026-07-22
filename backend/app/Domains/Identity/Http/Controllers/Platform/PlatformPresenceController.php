<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\PlatformPwaUserService;
use App\Domains\Identity\Services\TenantPresenceService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformPresenceController extends Controller
{
    public function __construct(
        private readonly TenantPresenceService $presence,
        private readonly PlatformPwaUserService $pwaUsers,
    ) {}

    public function poke(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->presence->poke($tenant, $data['message'] ?? null);

        return ApiResponse::success($result, 'Poke sent', 201);
    }

    public function pwaUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['admin', 'member'])],
        ]);

        return ApiResponse::success($this->pwaUsers->listUsers($data['type'] ?? null));
    }

    public function pushPwaUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'url' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'string', Rule::in(['admin', 'member'])],
            'subscription_ids' => ['nullable', 'array', 'max:200'],
            'subscription_ids.*' => ['uuid'],
        ]);

        $result = $this->pwaUsers->push($data);

        return ApiResponse::success($result, 'Push dispatched', 201);
    }
}
