<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunSource;
use App\Domains\Marketing\Enums\MarketingRunStatus;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingRunService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly AudienceResolverService $audienceResolver,
        private readonly MarketingAudienceService $audienceService,
        private readonly TemplateRendererService $renderer,
        private readonly MarketingTemplateService $templateService,
        private readonly MarketingDispatchSimulationService $dispatchSimulation,
        private readonly BookingReminderAutomationService $bookingReminderAutomation,
        private readonly RebookingAutomationService $rebookingAutomation,
        private readonly ReviewRequestAutomationService $reviewRequestAutomation,
        private readonly WinBackAutomationService $winBackAutomation,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingRun::query()
            ->with(['campaign', 'createdBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        if (! empty($filters['marketing_campaign_id'])) {
            $query->where('marketing_campaign_id', $filters['marketing_campaign_id']);
        }

        return $query->paginate($perPage);
    }

    public function show(string $id): MarketingRun
    {
        return $this->scope->findRun($id)->load(['campaign', 'createdBy', 'messages']);
    }

    public function find(string $id): MarketingRun
    {
        return $this->show($id);
    }

    public function messages(MarketingRun $run, array $filters = []): LengthAwarePaginator
    {
        return app(MarketingMessageService::class)->listForRun($run, $filters);
    }

    public function generateBookingReminders(array $options = []): MarketingRun
    {
        $options['run_source'] = MarketingRunSource::MANUAL;

        return $this->firstRunOrFail(
            $this->bookingReminderAutomation->generate($options),
            'No booking reminder messages were generated for the current filters.',
        );
    }

    public function generateRebooking(array $options = []): MarketingRun
    {
        $options['run_source'] = MarketingRunSource::MANUAL;

        return $this->firstRunOrFail(
            $this->rebookingAutomation->generate($options),
            'No rebooking nudge messages were generated for the current filters.',
        );
    }

    public function generateReviewRequests(array $options = []): MarketingRun
    {
        $options['run_source'] = MarketingRunSource::MANUAL;

        return $this->firstRunOrFail(
            $this->reviewRequestAutomation->generate($options),
            'No review request messages were generated for the current filters.',
        );
    }

    public function generateWinBack(array $options = []): MarketingRun
    {
        $options['run_source'] = MarketingRunSource::MANUAL;

        return $this->firstRunOrFail(
            $this->winBackAutomation->generate($options),
            'No win-back messages were generated for the current filters.',
        );
    }

    public function dispatch(MarketingRun $run): MarketingRun
    {
        $this->dispatchRun($run, true);

        return $run->fresh(['campaign', 'createdBy', 'messages']);
    }

    /**
     * Preview a broadcast audience without persisting messages.
     *
     * @return array{
     *     channel: string,
     *     counts: array<string, mixed>,
     *     eligible_sample: array<int, array<string, mixed>>,
     *     skipped_sample: array<int, array<string, mixed>>,
     *     render_preview: array<string, mixed>|null
     * }
     */
    public function broadcastPreview(array $data): array
    {
        $data = $this->enrichFromCampaign($data);
        $rules = $this->resolveAudienceRules($data);
        $channel = $this->requireChannel($data);
        $resolved = $this->audienceResolver->resolve($rules, $channel);

        $renderPreview = null;
        if (! empty($data['template_id']) && $resolved['eligible']->isNotEmpty()) {
            $template = $this->templateService->find($data['template_id']);
            $tenant = Tenant::query()->findOrFail($this->scope->tenantId());
            $client = $resolved['eligible']->first();
            $payload = $this->renderer->buildPayload($client, $tenant, $client->primaryLocation);
            $renderPreview = $this->renderer->renderTemplate($template, $payload);
        }

        return [
            'channel' => $resolved['channel'],
            'counts' => $resolved['counts'],
            'eligible_sample' => $resolved['eligible']
                ->take(20)
                ->map(fn (Client $client) => [
                    'client_id' => $client->id,
                    'client_name' => $client->resolvedDisplayName(),
                    'recipient_address' => $client->getAttribute('marketing_recipient_address'),
                ])
                ->values()
                ->all(),
            'skipped_sample' => array_slice($resolved['skipped'], 0, 20),
            'render_preview' => $renderPreview,
        ];
    }

    /**
     * Create a broadcast run with rendered messages and optionally dispatch them.
     */
    public function broadcastDispatch(array $data): MarketingRun
    {
        $data = $this->enrichFromCampaign($data);
        $rules = $this->resolveAudienceRules($data);
        $channel = $this->requireChannel($data);

        if (empty($data['template_id'])) {
            throw ValidationException::withMessages(['template_id' => ['Template is required for broadcast dispatch.']]);
        }

        $template = $this->templateService->find($data['template_id']);
        $campaign = ! empty($data['marketing_campaign_id'])
            ? $this->scope->findCampaign($data['marketing_campaign_id'])
            : null;

        return DB::transaction(function () use ($data, $rules, $channel, $template, $campaign) {
            $tenantId = $this->scope->tenantId();
            $tenant = Tenant::query()->findOrFail($tenantId);
            $resolved = $this->audienceResolver->resolve($rules, $channel);

            $run = MarketingRun::query()->create([
                'tenant_id' => $tenantId,
                'marketing_campaign_id' => $campaign?->id,
                'trigger_type' => null,
                'run_source' => $data['run_source'] ?? MarketingRunSource::MANUAL,
                'status' => MarketingRunStatus::PROCESSING,
                'filters_json' => [
                    'audience_rules' => $rules,
                    'channel' => $channel,
                    'template_id' => $template->id,
                ],
                'started_at' => now(),
                'created_by_team_member_id' => $data['created_by_team_member_id'] ?? null,
            ]);

            foreach ($resolved['eligible'] as $client) {
                $payload = $this->renderer->buildPayload($client, $tenant, $client->primaryLocation);
                $rendered = $this->renderer->renderTemplate($template, $payload);

                MarketingMessage::query()->create([
                    'tenant_id' => $tenantId,
                    'marketing_campaign_id' => $campaign?->id,
                    'marketing_run_id' => $run->id,
                    'client_id' => $client->id,
                    'location_id' => $client->primary_location_id,
                    'channel' => $channel,
                    'purpose' => MarketingMessagePurpose::BROADCAST,
                    'status' => MarketingMessageStatus::PENDING,
                    'recipient_address' => $client->getAttribute('marketing_recipient_address'),
                    'subject' => $rendered['subject'],
                    'rendered_body_text' => $rendered['body_text'],
                    'rendered_body_html' => $rendered['body_html'],
                    'template_snapshot_json' => $rendered['template_snapshot'],
                    'variables_snapshot_json' => $rendered['variables_snapshot'],
                    'scheduled_for' => now(),
                ]);
            }

            foreach ($resolved['skipped'] as $skipped) {
                MarketingMessage::query()->create([
                    'tenant_id' => $tenantId,
                    'marketing_campaign_id' => $campaign?->id,
                    'marketing_run_id' => $run->id,
                    'client_id' => $skipped['client_id'],
                    'channel' => $channel,
                    'purpose' => MarketingMessagePurpose::BROADCAST,
                    'status' => MarketingMessageStatus::SKIPPED,
                    'skipped_reason' => $skipped['reason'],
                ]);
            }

            $run->summary_json = $resolved['counts'];
            $run->status = MarketingRunStatus::COMPLETED;
            $run->completed_at = now();
            $run->save();

            if ($campaign !== null) {
                $campaign->last_run_at = now();
                $campaign->save();
            }

            if (filter_var($data['dispatch'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $this->dispatchRun($run, (bool) ($data['simulate'] ?? true));
            }

            return $run->fresh(['messages', 'campaign']);
        });
    }

    /**
     * Run one or all automation generators.
     *
     * @return array<int, MarketingRun>
     */
    public function orchestrateAutomation(?string $triggerType = null, array $options = []): array
    {
        $runs = [];
        $options['run_source'] = $options['run_source'] ?? MarketingRunSource::SCHEDULER;

        $map = [
            MarketingTriggerType::BOOKING_REMINDER => $this->bookingReminderAutomation,
            MarketingTriggerType::REBOOKING_NUDGE => $this->rebookingAutomation,
            MarketingTriggerType::REVIEW_REQUEST => $this->reviewRequestAutomation,
            MarketingTriggerType::WIN_BACK => $this->winBackAutomation,
        ];

        if ($triggerType !== null) {
            if (! isset($map[$triggerType])) {
                throw ValidationException::withMessages(['trigger_type' => ['Unsupported automation trigger.']]);
            }

            return $map[$triggerType]->generate($options);
        }

        foreach ($map as $service) {
            $runs = array_merge($runs, $service->generate($options));
        }

        return $runs;
    }

    /**
     * Dispatch pending messages for a run (simulation by default until Module 13 providers ship).
     *
     * @return array{processed: int, sent: int, failed: int, skipped: int}
     */
    public function dispatchRun(MarketingRun $run, bool $simulate = true): array
    {
        $this->scope->assertTenantModel($run);

        if ($simulate) {
            $summary = $this->dispatchSimulation->simulateRun($run->id);
            $run->refresh();
            $run->summary_json = array_merge($run->summary_json ?? [], ['dispatch' => $summary]);
            $run->save();

            return $summary;
        }

        throw ValidationException::withMessages([
            'dispatch' => ['Live provider dispatch is not configured. Use simulation mode.'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAudienceRules(array $data): array
    {
        if (! empty($data['audience_id'])) {
            $audience = $this->scope->findAudience($data['audience_id']);

            return $audience->rules_json ?? [];
        }

        return $this->audienceService->validateRules(
            $data['audience_rules'] ?? $data['audience_rules_json'] ?? $data['rules_json'] ?? $data['rules'] ?? [],
        );
    }

    private function requireChannel(array $data): string
    {
        $channel = $data['channel'] ?? null;

        if ($channel === null) {
            throw ValidationException::withMessages(['channel' => ['Channel is required.']]);
        }

        return $channel;
    }

    /**
     * @param  array<int, MarketingRun>  $runs
     */
    private function firstRunOrFail(array $runs, string $message): MarketingRun
    {
        if ($runs === []) {
            throw ValidationException::withMessages(['run' => [$message]]);
        }

        return $runs[0]->fresh(['campaign', 'createdBy', 'messages']);
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichFromCampaign(array $data): array
    {
        if (empty($data['marketing_campaign_id'])) {
            return $data;
        }

        $campaign = $this->scope->findCampaign($data['marketing_campaign_id']);

        $data['channel'] ??= $campaign->channel;
        $data['template_id'] ??= $campaign->template_id;
        $data['audience_rules_json'] ??= $campaign->audience_rules_json;
        $data['location_id'] ??= $campaign->location_id;

        return $data;
    }
}
