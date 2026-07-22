<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\PlatformNotificationService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformNotificationController extends Controller
{
    public function __construct(
        private readonly PlatformNotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'items' => $this->notifications->listForUser($user),
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->notifications->markRead($request->user(), $id);

        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ], 'Marked read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $marked = $this->notifications->markAllRead($request->user());

        return ApiResponse::success([
            'marked' => $marked,
            'unread_count' => 0,
        ], 'All notifications marked read');
    }
}
