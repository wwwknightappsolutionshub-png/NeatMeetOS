<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingWorkflowStatus;
use App\Domains\Marketing\Enums\MarketingWorkflowStepType;
use App\Domains\Marketing\Enums\MarketingWorkflowTrigger;
use App\Domains\Marketing\Models\MarketingAutomationWorkflow;
use App\Domains\Marketing\Models\MarketingWorkflowStep;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingAutomationWorkflowService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingAutomationWorkflow::query()
            ->with(['template', 'createdBy'])
            ->withCount('steps')
            ->orderBy('name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingAutomationWorkflow
    {
        return $this->scope->findWorkflow($id)->load(['template', 'createdBy', 'steps.template']);
    }

    public function create(array $data): MarketingAutomationWorkflow
    {
        $tenantId = $this->scope->tenantId();
        $this->validateTrigger($data['trigger_type'] ?? null);
        $this->validateChannel($data['channel'] ?? null);

        if (! empty($data['template_id'])) {
            $this->scope->findTemplate($data['template_id']);
        }

        $slug = $this->uniqueSlug($data['slug'] ?? $data['name'], $tenantId);

        return DB::transaction(function () use ($tenantId, $data, $slug) {
            $workflow = MarketingAutomationWorkflow::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'trigger_type' => $data['trigger_type'],
                'channel' => $data['channel'],
                'status' => $data['status'] ?? MarketingWorkflowStatus::DRAFT,
                'audience_rules_json' => $data['audience_rules'] ?? $data['audience_rules_json'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'delay_minutes' => (int) ($data['delay_minutes'] ?? 0),
                'cooldown_days' => $data['cooldown_days'] ?? null,
                'allow_repeat' => (bool) ($data['allow_repeat'] ?? false),
                'max_executions_per_client' => $data['max_executions_per_client'] ?? null,
                'settings_json' => $data['settings_json'] ?? null,
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            if (! empty($data['steps']) && is_array($data['steps'])) {
                $this->syncSteps($workflow, $data['steps']);
            }

            $this->auditLogger->log('marketing_workflow.created', $workflow, null, [
                'name' => $workflow->name,
                'trigger_type' => $workflow->trigger_type,
                'channel' => $workflow->channel,
            ]);

            return $workflow->fresh(['template', 'createdBy', 'steps']);
        });
    }

    public function update(MarketingAutomationWorkflow $workflow, array $data): MarketingAutomationWorkflow
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        if (array_key_exists('trigger_type', $data)) {
            $this->validateTrigger($data['trigger_type']);
        }
        if (array_key_exists('channel', $data)) {
            $this->validateChannel($data['channel']);
        }
        if (! empty($data['template_id'])) {
            $this->scope->findTemplate($data['template_id']);
        }

        if (array_key_exists('audience_rules', $data)) {
            $data['audience_rules_json'] = $data['audience_rules'];
        }

        $fields = array_intersect_key($data, array_flip([
            'name', 'description', 'trigger_type', 'channel', 'audience_rules_json',
            'template_id', 'delay_minutes', 'cooldown_days', 'allow_repeat',
            'max_executions_per_client', 'settings_json',
        ]));

        return DB::transaction(function () use ($workflow, $fields) {
            $old = $workflow->only(array_keys($fields));
            $workflow->fill($fields);
            $workflow->save();

            $this->auditLogger->log('marketing_workflow.updated', $workflow, $old, $workflow->only(array_keys($fields)));

            return $workflow->fresh(['template', 'createdBy', 'steps']);
        });
    }

    public function updateStatus(MarketingAutomationWorkflow $workflow, string $status): MarketingAutomationWorkflow
    {
        $this->scope->assertTenantModel($workflow);

        if (! in_array($status, MarketingWorkflowStatus::all(), true)) {
            throw ValidationException::withMessages(['status' => ['Invalid workflow status.']]);
        }

        if ($workflow->status === MarketingWorkflowStatus::ARCHIVED) {
            throw ValidationException::withMessages(['status' => ['Archived workflows cannot be modified.']]);
        }

        return DB::transaction(function () use ($workflow, $status) {
            $old = ['status' => $workflow->status];
            $workflow->status = $status;
            $workflow->save();

            $this->auditLogger->log('marketing_workflow.status_updated', $workflow, $old, ['status' => $status]);

            return $workflow->fresh(['template', 'createdBy', 'steps']);
        });
    }

    /**
     * Replace all workflow steps atomically.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function syncSteps(MarketingAutomationWorkflow $workflow, array $steps): MarketingAutomationWorkflow
    {
        $this->scope->assertTenantModel($workflow);
        $this->assertEditable($workflow);

        return DB::transaction(function () use ($workflow, $steps) {
            MarketingWorkflowStep::query()->where('workflow_id', $workflow->id)->delete();

            foreach (array_values($steps) as $position => $stepData) {
                $stepType = $stepData['step_type'] ?? MarketingWorkflowStepType::SEND_MESSAGE;

                if (! in_array($stepType, MarketingWorkflowStepType::all(), true)) {
                    throw ValidationException::withMessages(['steps' => ["Invalid step type: {$stepType}."]]);
                }

                if (! empty($stepData['template_id'])) {
                    $this->scope->findTemplate($stepData['template_id']);
                }

                MarketingWorkflowStep::query()->create([
                    'tenant_id' => $workflow->tenant_id,
                    'workflow_id' => $workflow->id,
                    'position' => $position,
                    'step_type' => $stepType,
                    'delay_minutes' => $stepData['delay_minutes'] ?? null,
                    'template_id' => $stepData['template_id'] ?? null,
                    'channel' => $stepData['channel'] ?? $workflow->channel,
                    'payload_json' => $stepData['payload'] ?? $stepData['payload_json'] ?? null,
                ]);
            }

            $this->auditLogger->log('marketing_workflow.steps_updated', $workflow, null, [
                'step_count' => count($steps),
            ]);

            return $workflow->fresh(['template', 'createdBy', 'steps.template']);
        });
    }

    private function assertEditable(MarketingAutomationWorkflow $workflow): void
    {
        if ($workflow->status === MarketingWorkflowStatus::ARCHIVED) {
            throw ValidationException::withMessages(['workflow' => ['Archived workflows cannot be edited.']]);
        }
    }

    private function validateTrigger(?string $trigger): void
    {
        if ($trigger === null || ! in_array($trigger, MarketingWorkflowTrigger::all(), true)) {
            throw ValidationException::withMessages(['trigger_type' => ['Invalid workflow trigger type.']]);
        }
    }

    private function validateChannel(?string $channel): void
    {
        if ($channel === null || ! in_array($channel, MarketingChannel::all(), true)) {
            throw ValidationException::withMessages(['channel' => ['Invalid marketing channel.']]);
        }
    }

    private function uniqueSlug(string $name, string $tenantId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (MarketingAutomationWorkflow::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
