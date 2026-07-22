<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunStatus;
use App\Domains\Marketing\Http\Resources\MarketingMessageResource;
use App\Domains\Marketing\Http\Resources\MarketingRunResource;
use App\Domains\Marketing\Services\MarketingRunService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingRunController extends Controller
{
    public function __construct(
        private readonly MarketingRunService $runService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'marketing_campaign_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(MarketingRunStatus::all())],
            'trigger_type' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingRunResource::collection($this->runService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingRunResource($this->runService->find($id)));
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MarketingMessageStatus::all())],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
        ]);

        $messages = $this->runService->messages($this->scope->findRun($id), $filters);

        return ApiResponse::success(MarketingMessageResource::collection($messages));
    }

    public function broadcastPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marketing_campaign_id' => ['required', 'uuid'],
            'audience_rules' => ['nullable', 'array'],
            'location_id' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::success($this->runService->broadcastPreview($data));
    }

    public function broadcastDispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marketing_campaign_id' => ['required', 'uuid'],
            'audience_rules' => ['nullable', 'array'],
            'location_id' => ['nullable', 'uuid'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        $run = $this->runService->broadcastDispatch($data);

        return ApiResponse::success(new MarketingRunResource($run), 'Broadcast dispatched', 201);
    }

    public function generateBookingReminders(Request $request): JsonResponse
    {
        return $this->runFromGeneration(
            $this->runService->generateBookingReminders($this->generationFilters($request)),
            'Booking reminders generated',
        );
    }

    public function generateRebooking(Request $request): JsonResponse
    {
        return $this->runFromGeneration(
            $this->runService->generateRebooking($this->generationFilters($request)),
            'Rebooking nudges generated',
        );
    }

    public function generateReviewRequests(Request $request): JsonResponse
    {
        return $this->runFromGeneration(
            $this->runService->generateReviewRequests($this->generationFilters($request)),
            'Review requests generated',
        );
    }

    public function generateWinBack(Request $request): JsonResponse
    {
        return $this->runFromGeneration(
            $this->runService->generateWinBack($this->generationFilters($request)),
            'Win-back messages generated',
        );
    }

    public function dispatch(string $id): JsonResponse
    {
        $run = $this->runService->dispatch($this->scope->findRun($id));

        return ApiResponse::success(new MarketingRunResource($run), 'Run dispatched');
    }

    /**
     * @return array<string, mixed>
     */
    private function generationFilters(Request $request): array
    {
        return $request->validate([
            'location_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
    }

    private function runFromGeneration(object $run, string $message): JsonResponse
    {
        return ApiResponse::success(new MarketingRunResource($run), $message, 201);
    }
}
