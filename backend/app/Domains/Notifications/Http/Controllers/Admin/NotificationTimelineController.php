<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationPurpose;
use App\Domains\Notifications\Services\NotificationScopeValidator;
use App\Domains\Notifications\Services\NotificationTimelineService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationTimelineController extends Controller
{
    public function __construct(
        private readonly NotificationTimelineService $timelineService,
        private readonly NotificationScopeValidator $scope,
    ) {}

    public function show(Request $request, string $clientId): JsonResponse
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(NotificationChannel::all())],
            'purpose' => ['nullable', Rule::in(NotificationPurpose::all())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $client = $this->scope->findClient($clientId);

        return ApiResponse::success($this->timelineService->forClient($client, $filters));
    }
}
