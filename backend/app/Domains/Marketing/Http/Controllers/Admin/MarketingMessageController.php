<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Http\Resources\MarketingMessageResource;
use App\Domains\Marketing\Services\MarketingDeliveryService;
use App\Domains\Marketing\Services\MarketingMessageService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingMessageController extends Controller
{
    public function __construct(
        private readonly MarketingMessageService $messageService,
        private readonly MarketingDeliveryService $deliveryService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MarketingMessageStatus::all())],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'client_id' => ['nullable', 'uuid'],
            'workflow_execution_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(MarketingMessageResource::collection($this->messageService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingMessageResource($this->messageService->find($id)));
    }

    public function markDelivered(string $id): JsonResponse
    {
        $message = $this->deliveryService->markDelivered($this->scope->findMessage($id));

        return ApiResponse::success(new MarketingMessageResource($message), 'Message marked delivered');
    }

    public function markOpened(string $id): JsonResponse
    {
        $message = $this->deliveryService->markOpened($this->scope->findMessage($id));

        return ApiResponse::success(new MarketingMessageResource($message), 'Message marked opened');
    }

    public function markClicked(string $id): JsonResponse
    {
        $message = $this->deliveryService->markClicked($this->scope->findMessage($id));

        return ApiResponse::success(new MarketingMessageResource($message), 'Message marked clicked');
    }

    public function markFailed(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'failure_category' => ['nullable', 'string', 'max:100'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = $this->deliveryService->markFailed(
            $this->scope->findMessage($id),
            $data['failure_category'] ?? null,
            $data['error_message'] ?? null,
        );

        return ApiResponse::success(new MarketingMessageResource($message), 'Message marked failed');
    }

    public function unsubscribe(string $id): JsonResponse
    {
        $message = $this->deliveryService->unsubscribe($this->scope->findMessage($id));

        return ApiResponse::success(new MarketingMessageResource($message), 'Recipient unsubscribed');
    }
}
