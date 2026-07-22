<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantOwnerPushSubscriptionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantOwnerPushSubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantOwnerPushSubscriptionService $subscriptions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['sometimes', 'array'],
            'keys.p256dh' => ['nullable', 'string', 'max:512'],
            'keys.auth' => ['nullable', 'string', 'max:512'],
            'p256dh' => ['nullable', 'string', 'max:512'],
            'auth' => ['nullable', 'string', 'max:512'],
        ]);

        $result = $this->subscriptions->save($request->user(), $data);

        return ApiResponse::success($result, 'Push subscription saved', 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $this->subscriptions->remove($request->user(), $data['endpoint']);

        return ApiResponse::success(null, 'Push subscription removed');
    }
}
