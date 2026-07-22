<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingExecutionStatus;
use App\Domains\Marketing\Http\Resources\MarketingWorkflowExecutionResource;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Domains\Marketing\Services\MarketingWorkflowExecutionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingWorkflowExecutionController extends Controller
{
    public function __construct(
        private readonly MarketingWorkflowExecutionService $executionService,
        private readonly MarketingAutomationTriggerService $triggerService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MarketingExecutionStatus::all())],
            'workflow_id' => ['nullable', 'uuid'],
            'trigger_type' => ['nullable', 'string', 'max:100'],
            'client_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(MarketingWorkflowExecutionResource::collection($this->executionService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingWorkflowExecutionResource($this->executionService->find($id)));
    }

    public function cancel(string $id): JsonResponse
    {
        $execution = $this->executionService->cancel($this->scope->findExecution($id));

        return ApiResponse::success(new MarketingWorkflowExecutionResource($execution), 'Execution cancelled');
    }

    public function process(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $summary = $this->executionService->processQueued($data['limit'] ?? 50);

        return ApiResponse::success($summary, 'Queued executions processed');
    }

    public function runBirthday(Request $request): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $result = $this->triggerService->runBirthdayAutomations($teamMember?->id);

        return ApiResponse::success([
            'matched' => $result['matched'],
            'executions' => MarketingWorkflowExecutionResource::collection(collect($result['executions'])),
        ], 'Birthday automations executed', 201);
    }
}
