<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowStepType;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingWorkflowStep;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Granular step management for automation workflows.
 *
 * Complements the atomic {@see MarketingAutomationWorkflowService::syncSteps()}
 * replace path with individual add/update/reorder/archive operations required by
 * the admin step editor.
 */
class MarketingWorkflowStepService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function addStep(MarketingAutomationWorkflow $workflow, array $data): MarketingWorkflowStep
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        $stepType = $data['step_type'] ?? MarketingWorkflowStepType::SEND_MESSAGE;
        $this->validateStepType($stepType);

        if (! empty($data['template_id'])) {
            $this->scope->findTemplate($data['template_id']);
        }

        return DB::transaction(function () use ($workflow, $data, $stepType) {
            $position = array_key_exists('position', $data) && $data['position'] !== null
                ? (int) $data['position']
                : (int) (MarketingWorkflowStep::query()->where('workflow_id', $workflow->id)->max('position') + 1);

            $step = MarketingWorkflowStep::query()->create([
                'tenant_id' => $workflow->tenant_id,
                'workflow_id' => $workflow->id,
                'position' => $position,
                'step_type' => $stepType,
                'delay_minutes' => $data['delay_minutes'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'channel' => $data['channel'] ?? $workflow->channel,
                'payload_json' => $data['payload'] ?? $data['payload_json'] ?? null,
            ]);

            $this->auditLogger->log('marketing_workflow_step.created', $step, null, [
                'workflow_id' => $workflow->id,
                'step_type' => $step->step_type,
                'position' => $step->position,
            ]);

            return $step->fresh(['template']);
        });
    }

    public function updateStep(MarketingAutomationWorkflow $workflow, string $stepId, array $data): MarketingWorkflowStep
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        $step = $this->scope->findWorkflowStep($workflow, $stepId);

        if (array_key_exists('step_type', $data)) {
            $this->validateStepType($data['step_type']);
        }
        if (! empty($data['template_id'])) {
            $this->scope->findTemplate($data['template_id']);
        }
        if (array_key_exists('payload', $data)) {
            $data['payload_json'] = $data['payload'];
        }

        $fields = array_intersect_key($data, array_flip([
            'step_type', 'delay_minutes', 'template_id', 'channel', 'payload_json', 'position',
        ]));

        return DB::transaction(function () use ($step, $workflow, $fields) {
            $old = $step->only(array_keys($fields));
            $step->fill($fields);
            $step->save();

            $this->auditLogger->log('marketing_workflow_step.updated', $step, $old, $step->only(array_keys($fields)) + [
                'workflow_id' => $workflow->id,
            ]);

            return $step->fresh(['template']);
        });
    }

    /**
     * Reorder steps to match the given ordered list of step ids.
     *
     * @param  array<int, string>  $orderedStepIds
     */
    public function reorder(MarketingAutomationWorkflow $workflow, array $orderedStepIds): MarketingAutomationWorkflow
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        $steps = MarketingWorkflowStep::query()
            ->where('workflow_id', $workflow->id)
            ->get()
            ->keyBy('id');

        $incoming = array_values(array_unique($orderedStepIds));

        if (count($incoming) !== $steps->count() || $steps->keys()->diff($incoming)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'step_ids' => ['The reorder list must contain every step for this workflow exactly once.'],
            ]);
        }

        return DB::transaction(function () use ($workflow, $incoming, $steps) {
            foreach ($incoming as $position => $stepId) {
                $step = $steps->get($stepId);
                $step->position = $position;
                $step->save();
            }

            $this->auditLogger->log('marketing_workflow_step.reordered', $workflow, null, [
                'workflow_id' => $workflow->id,
                'order' => $incoming,
            ]);

            return $workflow->fresh(['steps.template']);
        });
    }

    public function deleteStep(MarketingAutomationWorkflow $workflow, string $stepId): void
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        $step = $this->scope->findWorkflowStep($workflow, $stepId);

        DB::transaction(function () use ($workflow, $step) {
            $this->auditLogger->log('marketing_workflow_step.archived', $step, [
                'step_type' => $step->step_type,
                'position' => $step->position,
            ], ['workflow_id' => $workflow->id]);

            $step->delete();

            // Compact remaining positions so ordering stays contiguous.
            $remaining = MarketingWorkflowStep::query()
                ->where('workflow_id', $workflow->id)
                ->orderBy('position')
                ->get();

            foreach ($remaining->values() as $position => $remainingStep) {
                if ($remainingStep->position !== $position) {
                    $remainingStep->position = $position;
                    $remainingStep->save();
                }
            }
        });
    }

    private function assertEditable(MarketingAutomationWorkflow $workflow): void
    {
        if ($workflow->status === MarketingWorkflowStatus::ARCHIVED) {
            throw ValidationException::withMessages(['workflow' => ['Archived workflows cannot be edited.']]);
        }
    }

    private function validateStepType(string $stepType): void
    {
        if (! in_array($stepType, MarketingWorkflowStepType::all(), true)) {
            throw ValidationException::withMessages(['step_type' => ["Invalid step type: {$stepType}."]]);
        }
    }
}
