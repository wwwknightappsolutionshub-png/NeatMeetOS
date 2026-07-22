<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\PlatformTenantBroadcastService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantBroadcastController extends Controller
{
    public function __construct(
        private readonly PlatformTenantBroadcastService $broadcasts,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'href' => ['nullable', 'string', 'max:500'],
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'send_email' => ['sometimes', 'boolean'],
            'send_push' => ['sometimes', 'boolean'],
        ]);

        $result = $this->broadcasts->broadcast($data);

        return ApiResponse::success($result, 'Broadcast sent', 201);
    }
}
