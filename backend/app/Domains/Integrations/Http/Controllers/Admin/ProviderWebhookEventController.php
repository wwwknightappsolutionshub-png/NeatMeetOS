<?php

namespace App\Domains\Integrations\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Enums\ProviderWebhookProcessingStatus;
use App\Domains\Integrations\Http\Resources\ProviderWebhookEventResource;
use App\Domains\Integrations\Services\ProviderWebhookEventService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderWebhookEventController extends Controller
{
    public function __construct(
        private readonly ProviderWebhookEventService $events,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'driver' => ['nullable', Rule::in(ProviderDriver::all())],
            'category' => ['nullable', Rule::in(ProviderCategory::all())],
            'processing_status' => ['nullable', Rule::in(ProviderWebhookProcessingStatus::all())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            ProviderWebhookEventResource::collection($this->events->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ProviderWebhookEventResource($this->events->find($id)));
    }
}
