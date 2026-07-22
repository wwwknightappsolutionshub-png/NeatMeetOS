<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingExecutionStatus;
use App\Domains\Marketing\Enums\MarketingExecutionStepStatus;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowStepType;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Marketing\Models\MarketingWorkflowExecutionStep;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingWorkflowExecutionService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingEligibilityService $eligibility,
        private readonly MarketingDeliveryService $delivery,
        private readonly TemplateRendererService $renderer,
        private readonly MarketingTemplateService $templateService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingWorkflowExecution::query()
            ->with(['workflow', 'client'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['workflow_id'])) {
            $query->where('workflow_id', $filters['workflow_id']);
        }

        if (! empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingWorkflowExecution
    {
        return $this->scope->findExecution($id)->load([
            'workflow.template',
            'client',
            'steps.message',
            'messages',
        ]);
    }

    /**
     * Create a workflow execution for a client + trigger context.
     * Returns null if the execution is skipped (ineligible).
     */
    public function createExecution(
        MarketingAutomationWorkflow $workflow,
        Client $client,
        string $triggerType,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $context = [],
        ?string $teamMemberId = null,
        bool $processImmediately = true,
    ): ?MarketingWorkflowExecution {
        $this->scope->assertTenantModel($workflow);
        $this->scope->assertTenantModel($client);

        if ($workflow->status !== MarketingWorkflowStatus::ACTIVE) {
            return null;
        }

        $channel = $workflow->channel;
        $eligibility = $this->eligibility->evaluate($client, $channel);

        if (! $eligibility['eligible']) {
            return $this->createSkippedExecution($workflow, $client, $triggerType, $referenceType, $referenceId, $eligibility['skipped_reason'], $context, $teamMemberId);
        }

        $constraints = $this->eligibility->evaluateWorkflowConstraints(
            $client,
            $workflow->id,
            $workflow->cooldown_days,
            (bool) $workflow->allow_repeat,
            $workflow->max_executions_per_client,
        );

        if (! $constraints['eligible']) {
            return $this->createSkippedExecution($workflow, $client, $triggerType, $referenceType, $referenceId, $constraints['skipped_reason'], $context, $teamMemberId);
        }

        $delayMinutes = (int) $workflow->delay_minutes;
        $scheduledFor = $delayMinutes > 0 ? now()->addMinutes($delayMinutes) : now();

        return DB::transaction(function () use (
            $workflow, $client, $triggerType, $referenceType, $referenceId,
            $context, $teamMemberId, $scheduledFor, $processImmediately,
        ) {
            $execution = MarketingWorkflowExecution::query()->create([
                'tenant_id' => $workflow->tenant_id,
                'workflow_id' => $workflow->id,
                'client_id' => $client->id,
                'trigger_type' => $triggerType,
                'trigger_reference_type' => $referenceType,
                'trigger_reference_id' => $referenceId,
                'status' => MarketingExecutionStatus::QUEUED,
                'scheduled_for' => $scheduledFor,
                'context_json' => $context,
                'created_by_team_member_id' => $teamMemberId,
            ]);

            $workflowSteps = $workflow->relationLoaded('steps')
                ? $workflow->steps
                : $workflow->steps()->orderBy('position')->get();

            if ($workflowSteps->isEmpty()) {
                MarketingWorkflowExecutionStep::query()->create([
                    'tenant_id' => $workflow->tenant_id,
                    'workflow_execution_id' => $execution->id,
                    'workflow_step_id' => null,
                    'position' => 0,
                    'step_type' => MarketingWorkflowStepType::SEND_MESSAGE,
                    'status' => MarketingExecutionStepStatus::QUEUED,
                    'scheduled_for' => $scheduledFor,
                ]);
            } else {
                foreach ($workflowSteps as $step) {
                    $stepDelay = (int) ($step->delay_minutes ?? 0);
                    MarketingWorkflowExecutionStep::query()->create([
                        'tenant_id' => $workflow->tenant_id,
                        'workflow_execution_id' => $execution->id,
                        'workflow_step_id' => $step->id,
                        'position' => $step->position,
                        'step_type' => $step->step_type,
                        'status' => MarketingExecutionStepStatus::QUEUED,
                        'scheduled_for' => $scheduledFor->copy()->addMinutes($stepDelay),
                    ]);
                }
            }

            $workflow->last_triggered_at = now();
            $workflow->save();

            $this->auditLogger->log('marketing_workflow_execution.created', $execution, null, [
                'workflow_id' => $workflow->id,
                'client_id' => $client->id,
                'trigger_type' => $triggerType,
            ]);

            if ($processImmediately && $scheduledFor->lte(now())) {
                return $this->processExecution($execution->fresh(['steps', 'workflow.steps.template', 'client']));
            }

            return $execution->fresh(['steps', 'workflow', 'client']);
        });
    }

    /**
     * Process all queued steps for an execution synchronously.
     */
    public function processExecution(MarketingWorkflowExecution $execution): MarketingWorkflowExecution
    {
        $this->scope->assertTenantModel($execution);

        if (in_array($execution->status, MarketingExecutionStatus::terminal(), true)) {
            return $execution;
        }

        $execution->status = MarketingExecutionStatus::RUNNING;
        $execution->started_at = $execution->started_at ?? now();
        $execution->save();

        $execution->load(['steps', 'workflow.steps.template', 'workflow.template', 'client']);

        $hasFailure = false;
        $failureReason = null;

        foreach ($execution->steps->sortBy('position') as $execStep) {
            if ($execStep->status !== MarketingExecutionStepStatus::QUEUED) {
                continue;
            }

            if ($execStep->scheduled_for !== null && $execStep->scheduled_for->isFuture()) {
                continue;
            }

            try {
                $this->processStep($execution, $execStep);
            } catch (\Throwable $e) {
                $execStep->status = MarketingExecutionStepStatus::FAILED;
                $execStep->failure_reason = $e->getMessage();
                $execStep->processed_at = now();
                $execStep->save();
                $hasFailure = true;
                $failureReason = $e->getMessage();
            }
        }

        $execution->refresh();

        $pendingSteps = $execution->steps()
            ->where('status', MarketingExecutionStepStatus::QUEUED)
            ->exists();

        if ($hasFailure) {
            $execution->status = MarketingExecutionStatus::FAILED;
            $execution->failure_reason = $failureReason;
            $execution->completed_at = now();
            $execution->save();
            $this->auditLogger->log('marketing_workflow_execution.failed', $execution, null, ['reason' => $failureReason]);
        } elseif ($pendingSteps) {
            $execution->status = MarketingExecutionStatus::QUEUED;
            $execution->save();
        } else {
            $execution->status = MarketingExecutionStatus::COMPLETED;
            $execution->completed_at = now();
            $execution->save();
            $this->auditLogger->log('marketing_workflow_execution.completed', $execution, null, [
                'steps_processed' => $execution->steps()->where('status', MarketingExecutionStepStatus::COMPLETED)->count(),
            ]);
        }

        return $execution->fresh(['steps.message', 'workflow', 'client', 'messages']);
    }

    /**
     * Process all queued executions that are due.
     *
     * @return array{processed: int, completed: int, failed: int}
     */
    public function processQueued(?int $limit = 50): array
    {
        $executions = MarketingWorkflowExecution::query()
            ->where('tenant_id', $this->scope->tenantId())
            ->where('status', MarketingExecutionStatus::QUEUED)
            ->where(function ($q) {
                $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        $completed = 0;
        $failed = 0;

        foreach ($executions as $execution) {
            $result = $this->processExecution($execution);
            if ($result->status === MarketingExecutionStatus::COMPLETED) {
                $completed++;
            } elseif ($result->status === MarketingExecutionStatus::FAILED) {
                $failed++;
            }
        }

        return ['processed' => $executions->count(), 'completed' => $completed, 'failed' => $failed];
    }

    public function cancel(MarketingWorkflowExecution $execution): MarketingWorkflowExecution
    {
        $this->scope->assertTenantModel($execution);

        if (in_array($execution->status, MarketingExecutionStatus::terminal(), true)) {
            throw ValidationException::withMessages(['status' => ['Execution is already in a terminal state.']]);
        }

        return DB::transaction(function () use ($execution) {
            $old = ['status' => $execution->status];
            $execution->status = MarketingExecutionStatus::CANCELLED;
            $execution->cancelled_at = now();
            $execution->save();

            $execution->steps()
                ->where('status', MarketingExecutionStepStatus::QUEUED)
                ->update(['status' => MarketingExecutionStepStatus::SKIPPED, 'processed_at' => now()]);

            $this->auditLogger->log('marketing_workflow_execution.cancelled', $execution, $old, ['status' => MarketingExecutionStatus::CANCELLED]);

            return $execution->fresh(['steps', 'workflow', 'client']);
        });
    }

    private function processStep(MarketingWorkflowExecution $execution, MarketingWorkflowExecutionStep $execStep): void
    {
        $execStep->status = MarketingExecutionStepStatus::PROCESSING;
        $execStep->save();

        if ($execStep->step_type === MarketingWorkflowStepType::WAIT) {
            $execStep->status = MarketingExecutionStepStatus::COMPLETED;
            $execStep->processed_at = now();
            $execStep->save();

            return;
        }

        if ($execStep->step_type !== MarketingWorkflowStepType::SEND_MESSAGE) {
            $execStep->status = MarketingExecutionStepStatus::SKIPPED;
            $execStep->failure_reason = 'step_type_deferred';
            $execStep->processed_at = now();
            $execStep->save();

            return;
        }

        $workflow = $execution->workflow;
        $client = $execution->client;
        $channel = $workflow->channel;

        $eligibility = $this->eligibility->evaluate($client, $channel);
        if (! $eligibility['eligible']) {
            $execStep->status = MarketingExecutionStepStatus::SKIPPED;
            $execStep->failure_reason = $eligibility['skipped_reason'];
            $execStep->processed_at = now();
            $execStep->save();

            return;
        }

        $workflowStep = $execStep->workflow_step_id !== null
            ? $workflow->steps->firstWhere('id', $execStep->workflow_step_id)
            : null;

        $templateId = $workflowStep?->template_id ?? $workflow->template_id;
        $template = $templateId !== null
            ? $this->templateService->find($templateId)
            : $this->templateService->resolveForTrigger($workflow->trigger_type, $channel, null);

        if ($template === null) {
            $execStep->status = MarketingExecutionStepStatus::FAILED;
            $execStep->failure_reason = 'missing_template';
            $execStep->processed_at = now();
            $execStep->save();

            return;
        }

        $tenant = Tenant::query()->findOrFail($execution->tenant_id);
        $payload = $this->renderer->buildPayload($client, $tenant, $client->primaryLocation);
        $rendered = $this->renderer->renderTemplate($template, $payload);

        $message = MarketingMessage::query()->create([
            'tenant_id' => $execution->tenant_id,
            'workflow_execution_id' => $execution->id,
            'workflow_step_id' => $execStep->workflow_step_id,
            'client_id' => $client->id,
            'channel' => $channel,
            'purpose' => MarketingMessagePurpose::WORKFLOW,
            'status' => MarketingMessageStatus::PENDING,
            'recipient_address' => $eligibility['recipient_address'],
            'subject' => $rendered['subject'],
            'rendered_body_text' => $rendered['body_text'],
            'rendered_body_html' => $rendered['body_html'],
            'template_snapshot_json' => $rendered['template_snapshot'],
            'variables_snapshot_json' => $rendered['variables_snapshot'],
            'scheduled_for' => now(),
        ]);

        $message = $this->delivery->dispatchMessage($message);

        $execStep->message_id = $message->id;
        $execStep->status = $message->status === MarketingMessageStatus::FAILED
            ? MarketingExecutionStepStatus::FAILED
            : MarketingExecutionStepStatus::COMPLETED;
        $execStep->failure_reason = $message->status === MarketingMessageStatus::FAILED ? ($message->error_message ?? 'dispatch_failed') : null;
        $execStep->processed_at = now();
        $execStep->save();
    }

    private function createSkippedExecution(
        MarketingAutomationWorkflow $workflow,
        Client $client,
        string $triggerType,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
        array $context,
        ?string $teamMemberId,
    ): MarketingWorkflowExecution {
        $execution = MarketingWorkflowExecution::query()->create([
            'tenant_id' => $workflow->tenant_id,
            'workflow_id' => $workflow->id,
            'client_id' => $client->id,
            'trigger_type' => $triggerType,
            'trigger_reference_type' => $referenceType,
            'trigger_reference_id' => $referenceId,
            'status' => MarketingExecutionStatus::SKIPPED,
            'failure_reason' => $reason,
            'completed_at' => now(),
            'context_json' => $context,
            'created_by_team_member_id' => $teamMemberId,
        ]);

        $this->auditLogger->log('marketing_workflow_execution.created', $execution, null, [
            'workflow_id' => $workflow->id,
            'client_id' => $client->id,
            'skipped_reason' => $reason,
        ]);

        return $execution;
    }
}
