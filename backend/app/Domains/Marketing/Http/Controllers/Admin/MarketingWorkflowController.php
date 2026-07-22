<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Http\Resources\MarketingWorkflowExecutionResource;
use App\Domains\Marketing\Http\Resources\MarketingWorkflowResource;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Domains\Marketing\Services\MarketingAutomationWorkflowService;
use App\Domains\Marketing\Services\MarketingScopeValidator;
use App\Domains\Marketing\Services\MarketingWorkflowExecutionService;
use App\Domains\Marketing\Services\MarketingWorkflowStepService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingWorkflowController extends Controller
{
    public function __construct(
        private readonly MarketingAutomationWorkflowService $workflowService,
        private readonly MarketingAutomationTriggerService $triggerService,
        private readonly MarketingWorkflowStepService $stepService,
        private readonly MarketingWorkflowExecutionService $executionService,
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MarketingWorkflowStatus::all())],
            'trigger_type' => ['nullable', Rule::in(MarketingWorkflowTrigger::all())],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(MarketingWorkflowResource::collection($this->workflowService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new MarketingWorkflowResource($this->workflowService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'trigger_type' => ['required', Rule::in(MarketingWorkflowTrigger::all())],
            'channel' => ['required', Rule::in(MarketingChannel::all())],
            'status' => ['nullable', Rule::in(MarketingWorkflowStatus::all())],
            'template_id' => ['nullable', 'uuid'],
            'audience_rules' => ['nullable', 'array'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
            'cooldown_days' => ['nullable', 'integer', 'min:0'],
            'allow_repeat' => ['nullable', 'boolean'],
            'max_executions_per_client' => ['nullable', 'integer', 'min:1'],
            'settings_json' => ['nullable', 'array'],
            'steps' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $data['created_by_team_member_id'] = $teamMember?->id;

        $workflow = $this->workflowService->create($data);

        return ApiResponse::success(new MarketingWorkflowResource($workflow), 'Workflow created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'trigger_type' => ['sometimes', Rule::in(MarketingWorkflowTrigger::all())],
            'channel' => ['sometimes', Rule::in(MarketingChannel::all())],
            'template_id' => ['nullable', 'uuid'],
            'audience_rules' => ['nullable', 'array'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
            'cooldown_days' => ['nullable', 'integer', 'min:0'],
            'allow_repeat' => ['nullable', 'boolean'],
            'max_executions_per_client' => ['nullable', 'integer', 'min:1'],
            'settings_json' => ['nullable', 'array'],
        ]);

        $workflow = $this->workflowService->update($this->scope->findWorkflow($id), $data);

        return ApiResponse::success(new MarketingWorkflowResource($workflow), 'Workflow updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(MarketingWorkflowStatus::all())],
        ]);

        $workflow = $this->workflowService->updateStatus($this->scope->findWorkflow($id), $data['status']);

        return ApiResponse::success(new MarketingWorkflowResource($workflow), 'Workflow status updated');
    }

    public function updateSteps(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'steps' => ['required', 'array'],
            'steps.*.step_type' => ['required', 'string'],
            'steps.*.delay_minutes' => ['nullable', 'integer', 'min:0'],
            'steps.*.template_id' => ['nullable', 'uuid'],
            'steps.*.channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'steps.*.payload' => ['nullable', 'array'],
        ]);

        $workflow = $this->workflowService->syncSteps($this->scope->findWorkflow($id), $data['steps']);

        return ApiResponse::success(new MarketingWorkflowResource($workflow), 'Workflow steps updated');
    }

    public function executions(Request $request, string $id): JsonResponse
    {
        $workflow = $this->scope->findWorkflow($id);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['workflow_id'] = $workflow->id;

        return ApiResponse::success(
            MarketingWorkflowExecutionResource::collection($this->executionService->list($filters))
        );
    }

    public function addStep(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'step_type' => ['required', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
            'template_id' => ['nullable', 'uuid'],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'payload' => ['nullable', 'array'],
        ]);

        $workflow = $this->scope->findWorkflow($id);
        $this->stepService->addStep($workflow, $data);

        return ApiResponse::success(new MarketingWorkflowResource($this->workflowService->find($id)), 'Step added', 201);
    }

    public function updateStep(Request $request, string $id, string $stepId): JsonResponse
    {
        $data = $request->validate([
            'step_type' => ['sometimes', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'delay_minutes' => ['nullable', 'integer', 'min:0'],
            'template_id' => ['nullable', 'uuid'],
            'channel' => ['nullable', Rule::in(MarketingChannel::all())],
            'payload' => ['nullable', 'array'],
        ]);

        $workflow = $this->scope->findWorkflow($id);
        $this->stepService->updateStep($workflow, $stepId, $data);

        return ApiResponse::success(new MarketingWorkflowResource($this->workflowService->find($id)), 'Step updated');
    }

    public function reorderSteps(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'step_ids' => ['required', 'array', 'min:1'],
            'step_ids.*' => ['uuid'],
        ]);

        $workflow = $this->scope->findWorkflow($id);
        $this->stepService->reorder($workflow, $data['step_ids']);

        return ApiResponse::success(new MarketingWorkflowResource($this->workflowService->find($id)), 'Steps reordered');
    }

    public function deleteStep(string $id, string $stepId): JsonResponse
    {
        $workflow = $this->scope->findWorkflow($id);
        $this->stepService->deleteStep($workflow, $stepId);

        return ApiResponse::success(new MarketingWorkflowResource($this->workflowService->find($id)), 'Step archived');
    }

    public function runTest(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
        ]);

        $workflow = $this->scope->findWorkflow($id);
        $client = $this->scope->findClient($data['client_id']);
        $teamMember = $request->attributes->get('team_member');

        $execution = $this->triggerService->testRun($workflow, $client, $teamMember?->id);

        return ApiResponse::success(new MarketingWorkflowExecutionResource($execution), 'Test run completed', 201);
    }
}
