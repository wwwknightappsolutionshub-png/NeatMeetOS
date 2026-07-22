<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunStatus;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingReportingService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
    ) {}

    /**
     * @return array{
     *     period: array{from: string|null, to: string|null},
     *     messages: array<string, int>,
     *     runs: array<string, int>,
     *     campaigns: array{total: int, active: int},
     *     channels: array<string, int>
     * }
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $messageQuery = MarketingMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);

        $messagesByStatus = (clone $messageQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $runsByStatus = MarketingRun::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $channels = (clone $messageQuery)
            ->select('channel', DB::raw('COUNT(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        $campaignTotal = MarketingCampaign::query()->where('tenant_id', $tenantId)->count();
        $campaignActive = MarketingCampaign::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'messages' => $this->normaliseStatusCounts($messagesByStatus, MarketingMessageStatus::all()),
            'runs' => $this->normaliseStatusCounts($runsByStatus, MarketingRunStatus::all()),
            'campaigns' => [
                'total' => $campaignTotal,
                'active' => $campaignActive,
            ],
            'channels' => $channels,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function campaigns(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $campaigns = MarketingCampaign::query()
            ->where('tenant_id', $tenantId)
            ->withCount([
                'runs as runs_count' => fn ($q) => $q->whereBetween('created_at', [$from, $to]),
                'messages as messages_count' => fn ($q) => $q->whereBetween('created_at', [$from, $to]),
            ])
            ->orderBy('name')
            ->get();

        return $campaigns->map(function (MarketingCampaign $campaign) use ($from, $to) {
            $messageStats = MarketingMessage::query()
                ->where('marketing_campaign_id', $campaign->id)
                ->whereBetween('created_at', [$from, $to])
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            return [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'campaign_type' => $campaign->campaign_type,
                'trigger_type' => $campaign->trigger_type,
                'channel' => $campaign->channel,
                'status' => $campaign->status,
                'runs_count' => (int) $campaign->runs_count,
                'messages_count' => (int) $campaign->messages_count,
                'messages_by_status' => $this->normaliseStatusCounts($messageStats, MarketingMessageStatus::all()),
                'last_run_at' => $campaign->last_run_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runs(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return MarketingRun::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['campaign:id,name,channel'])
            ->withCount([
                'messages as messages_total',
                'messages as messages_sent' => fn ($q) => $q->where('status', MarketingMessageStatus::SENT),
                'messages as messages_failed' => fn ($q) => $q->where('status', MarketingMessageStatus::FAILED),
                'messages as messages_skipped' => fn ($q) => $q->where('status', MarketingMessageStatus::SKIPPED),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MarketingRun $run) => [
                'run_id' => $run->id,
                'campaign_id' => $run->marketing_campaign_id,
                'campaign_name' => $run->campaign?->name,
                'trigger_type' => $run->trigger_type,
                'run_source' => $run->run_source,
                'status' => $run->status,
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'summary_json' => $run->summary_json,
                'messages_total' => (int) $run->messages_total,
                'messages_sent' => (int) $run->messages_sent,
                'messages_failed' => (int) $run->messages_failed,
                'messages_skipped' => (int) $run->messages_skipped,
            ])
            ->all();
    }

    /**
     * @return array{
     *     workflows: array{total: int, active: int, by_trigger: array<string, int>},
     *     executions: array<string, int>,
     *     messages: array<string, int>,
     *     suppressions: array{total: int, active: int, by_channel: array<string, int>}
     * }
     */
    public function automationSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $workflowTotal = \App\Domains\Marketing\Models\MarketingAutomationWorkflow::query()
            ->where('tenant_id', $tenantId)->count();
        $workflowActive = \App\Domains\Marketing\Models\MarketingAutomationWorkflow::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
        $byTrigger = \App\Domains\Marketing\Models\MarketingAutomationWorkflow::query()
            ->where('tenant_id', $tenantId)
            ->select('trigger_type', DB::raw('COUNT(*) as total'))
            ->groupBy('trigger_type')
            ->pluck('total', 'trigger_type')
            ->all();

        $executionsByStatus = \App\Domains\Marketing\Models\MarketingWorkflowExecution::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $messagesByStatus = MarketingMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('workflow_execution_id')
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $suppressionTotal = \App\Domains\Marketing\Models\MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)->count();
        $suppressionActive = \App\Domains\Marketing\Models\MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
        $suppressionsByChannel = \App\Domains\Marketing\Models\MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('channel', DB::raw('COUNT(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'workflows' => [
                'total' => $workflowTotal,
                'active' => $workflowActive,
                'by_trigger' => $byTrigger,
            ],
            'executions' => $this->normaliseStatusCounts($executionsByStatus, \App\Domains\Marketing\Enums\MarketingExecutionStatus::all()),
            'messages' => $this->normaliseStatusCounts($messagesByStatus, MarketingMessageStatus::all()),
            'suppressions' => [
                'total' => $suppressionTotal,
                'active' => $suppressionActive,
                'by_channel' => $suppressionsByChannel,
            ],
        ];
    }

    /**
     * Per-workflow performance summary (executions + messages counts by status).
     *
     * @return array<int, array<string, mixed>>
     */
    public function automationWorkflows(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return \App\Domains\Marketing\Models\MarketingAutomationWorkflow::query()
            ->where('tenant_id', $tenantId)
            ->withCount('steps')
            ->orderBy('name')
            ->get()
            ->map(function ($workflow) use ($tenantId, $from, $to) {
                $executions = \App\Domains\Marketing\Models\MarketingWorkflowExecution::query()
                    ->where('tenant_id', $tenantId)
                    ->where('workflow_id', $workflow->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->all();

                $messages = MarketingMessage::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('workflow_execution_id', function ($q) use ($tenantId, $workflow) {
                        $q->select('id')
                            ->from('marketing_workflow_executions')
                            ->where('tenant_id', $tenantId)
                            ->where('workflow_id', $workflow->id);
                    })
                    ->whereBetween('created_at', [$from, $to])
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->all();

                return [
                    'workflow_id' => $workflow->id,
                    'name' => $workflow->name,
                    'trigger_type' => $workflow->trigger_type,
                    'channel' => $workflow->channel,
                    'status' => $workflow->status,
                    'steps_count' => (int) $workflow->steps_count,
                    'last_triggered_at' => $workflow->last_triggered_at?->toIso8601String(),
                    'executions' => $this->normaliseStatusCounts($executions, \App\Domains\Marketing\Enums\MarketingExecutionStatus::all()),
                    'messages' => $this->normaliseStatusCounts($messages, MarketingMessageStatus::all()),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function automationExecutions(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return \App\Domains\Marketing\Models\MarketingWorkflowExecution::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['workflow:id,name,trigger_type,channel', 'client:id,first_name,last_name,display_name'])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($e) => [
                'execution_id' => $e->id,
                'workflow_id' => $e->workflow_id,
                'workflow_name' => $e->workflow?->name,
                'trigger_type' => $e->trigger_type,
                'client_id' => $e->client_id,
                'client_name' => $e->client?->resolvedDisplayName(),
                'status' => $e->status,
                'failure_reason' => $e->failure_reason,
                'scheduled_for' => $e->scheduled_for?->toIso8601String(),
                'started_at' => $e->started_at?->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
                'messages_count' => (int) $e->messages_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function automationMessages(?Carbon $from = null, ?Carbon $to = null): array
    {
        $tenantId = $this->scope->tenantId();
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        return MarketingMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('workflow_execution_id')
            ->whereBetween('created_at', [$from, $to])
            ->with(['client:id,first_name,last_name,display_name'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (MarketingMessage $m) => [
                'message_id' => $m->id,
                'workflow_execution_id' => $m->workflow_execution_id,
                'client_id' => $m->client_id,
                'client_name' => $m->client?->resolvedDisplayName(),
                'channel' => $m->channel,
                'status' => $m->status,
                'recipient_address' => $m->recipient_address,
                'sent_at' => $m->sent_at?->toIso8601String(),
                'delivered_at' => $m->delivered_at?->toIso8601String(),
                'failed_at' => $m->failed_at?->toIso8601String(),
                'failure_category' => $m->failure_category,
            ])
            ->all();
    }

    /**
     * @return array{by_reason: array<string, int>, by_channel: array<string, int>, active: int, total: int}
     */
    public function automationSuppressions(): array
    {
        $tenantId = $this->scope->tenantId();

        $byReason = \App\Domains\Marketing\Models\MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('reason', DB::raw('COUNT(*) as total'))
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->all();

        $byChannel = \App\Domains\Marketing\Models\MarketingContactSuppression::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('channel', DB::raw('COUNT(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->all();

        return [
            'total' => \App\Domains\Marketing\Models\MarketingContactSuppression::query()->where('tenant_id', $tenantId)->count(),
            'active' => \App\Domains\Marketing\Models\MarketingContactSuppression::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'by_reason' => $byReason,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $statuses
     * @return array<string, int>
     */
    private function normaliseStatusCounts(array $counts, array $statuses): array
    {
        $normalised = [];

        foreach ($statuses as $status) {
            $normalised[$status] = (int) ($counts[$status] ?? 0);
        }

        return $normalised;
    }
}
