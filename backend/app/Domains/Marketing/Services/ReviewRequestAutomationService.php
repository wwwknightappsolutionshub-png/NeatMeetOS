<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingCampaignStatus;
use App\Domains\Marketing\Enums\MarketingCampaignType;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Enums\MarketingRunSource;
use App\Domains\Marketing\Enums\MarketingRunStatus;
use App\Domains\Marketing\Enums\MarketingTriggerType;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use Illuminate\Support\Facades\DB;

class ReviewRequestAutomationService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingAutomationSettingService $automationSettings,
        private readonly MarketingEligibilityService $eligibility,
        private readonly TemplateRendererService $renderer,
        private readonly MarketingTemplateService $templateService,
    ) {}

    /**
     * @return array<int, MarketingRun>
     */
    public function generate(array $options = []): array
    {
        $settings = $this->automationSettings->getOrCreate();

        if (! $settings->review_request_enabled) {
            return [];
        }

        $delayHours = (int) $settings->review_request_delay_hours;
        $runSource = $options['run_source'] ?? MarketingRunSource::SCHEDULER;

        $campaigns = MarketingCampaign::query()
            ->where('campaign_type', MarketingCampaignType::AUTOMATION)
            ->where('trigger_type', MarketingTriggerType::REVIEW_REQUEST)
            ->where('status', MarketingCampaignStatus::ACTIVE)
            ->with('template')
            ->get();

        $runs = [];

        foreach ($campaigns as $campaign) {
            $runs[] = $this->generateForCampaign($campaign, $delayHours, $runSource, $options);
        }

        return $runs;
    }

    private function generateForCampaign(
        MarketingCampaign $campaign,
        int $delayHours,
        string $runSource,
        array $options,
    ): MarketingRun {
        return DB::transaction(function () use ($campaign, $delayHours, $runSource, $options) {
            $tenantId = $this->scope->tenantId();
            $tenant = Tenant::query()->findOrFail($tenantId);
            $links = $this->buildLinks($tenant);

            $rangeEnd = now()->subHours($delayHours);
            $rangeStart = $rangeEnd->copy()->subHours(1);

            $run = MarketingRun::query()->create([
                'tenant_id' => $tenantId,
                'marketing_campaign_id' => $campaign->id,
                'trigger_type' => MarketingTriggerType::REVIEW_REQUEST,
                'run_source' => $runSource,
                'status' => MarketingRunStatus::PROCESSING,
                'filters_json' => [
                    'delay_hours' => $delayHours,
                    'range_start' => $rangeStart->toIso8601String(),
                    'range_end' => $rangeEnd->toIso8601String(),
                    'location_id' => $campaign->location_id,
                ],
                'started_at' => now(),
                'created_by_team_member_id' => $options['created_by_team_member_id'] ?? null,
            ]);

            $appointments = Appointment::query()
                ->with(['client.primaryLocation', 'location', 'serviceLines'])
                ->where('status', Appointment::STATUS_COMPLETED)
                ->whereNotNull('client_id')
                ->where(function ($q) use ($rangeStart, $rangeEnd) {
                    $q->whereBetween('ends_at', [$rangeStart, $rangeEnd])
                        ->orWhere(function ($inner) use ($rangeStart, $rangeEnd) {
                            $inner->whereNull('ends_at')->whereBetween('starts_at', [$rangeStart, $rangeEnd]);
                        });
                })
                ->when($campaign->location_id, fn ($q) => $q->where('location_id', $campaign->location_id))
                ->orderBy('ends_at')
                ->get();

            $eligible = 0;
            $skipped = 0;
            $byReason = [];

            foreach ($appointments as $appointment) {
                if ($this->alreadyQueued($appointment->id)) {
                    continue;
                }

                $client = $appointment->client;
                if ($client === null) {
                    continue;
                }

                $context = ['location_ids' => $campaign->location_id ? [$campaign->location_id] : []];
                $check = $this->eligibility->evaluate($client, $campaign->channel, $context);

                if (! $check['eligible']) {
                    $this->createSkippedMessage($run, $campaign, $client, $appointment, $check['skipped_reason']);
                    $skipped++;
                    $byReason[$check['skipped_reason']] = ($byReason[$check['skipped_reason']] ?? 0) + 1;

                    continue;
                }

                $template = $campaign->template
                    ?? $this->templateService->resolveForTrigger(
                        MarketingTriggerType::REVIEW_REQUEST,
                        $campaign->channel,
                        $campaign->template_id,
                    );

                if ($template === null) {
                    $this->createSkippedMessage($run, $campaign, $client, $appointment, 'missing_template');
                    $skipped++;
                    $byReason['missing_template'] = ($byReason['missing_template'] ?? 0) + 1;

                    continue;
                }

                $payload = $this->renderer->buildPayload($client, $tenant, $appointment->location, $appointment, null, $links);
                $rendered = $this->renderer->renderTemplate($template, $payload);

                MarketingMessage::query()->create([
                    'tenant_id' => $tenantId,
                    'marketing_campaign_id' => $campaign->id,
                    'marketing_run_id' => $run->id,
                    'client_id' => $client->id,
                    'appointment_id' => $appointment->id,
                    'location_id' => $appointment->location_id,
                    'channel' => $campaign->channel,
                    'purpose' => MarketingMessagePurpose::REVIEW_REQUEST,
                    'status' => MarketingMessageStatus::PENDING,
                    'recipient_address' => $check['recipient_address'],
                    'subject' => $rendered['subject'],
                    'rendered_body_text' => $rendered['body_text'],
                    'rendered_body_html' => $rendered['body_html'],
                    'template_snapshot_json' => $rendered['template_snapshot'],
                    'variables_snapshot_json' => $rendered['variables_snapshot'],
                    'scheduled_for' => now(),
                ]);

                $eligible++;
            }

            $run->summary_json = [
                'appointments_matched' => $appointments->count(),
                'eligible' => $eligible,
                'skipped' => $skipped,
                'by_reason' => $byReason,
            ];
            $run->status = MarketingRunStatus::COMPLETED;
            $run->completed_at = now();
            $run->save();

            $campaign->last_run_at = now();
            $campaign->save();

            return $run->fresh(['messages', 'campaign']);
        });
    }

    private function alreadyQueued(string $appointmentId): bool
    {
        return MarketingMessage::query()
            ->where('appointment_id', $appointmentId)
            ->where('purpose', MarketingMessagePurpose::REVIEW_REQUEST)
            ->whereIn('status', [
                MarketingMessageStatus::PENDING,
                MarketingMessageStatus::PROCESSING,
                MarketingMessageStatus::SENT,
            ])
            ->exists();
    }

    private function createSkippedMessage(
        MarketingRun $run,
        MarketingCampaign $campaign,
        Client $client,
        Appointment $appointment,
        ?string $reason,
    ): void {
        MarketingMessage::query()->create([
            'tenant_id' => $run->tenant_id,
            'marketing_campaign_id' => $campaign->id,
            'marketing_run_id' => $run->id,
            'client_id' => $client->id,
            'appointment_id' => $appointment->id,
            'location_id' => $appointment->location_id,
            'channel' => $campaign->channel,
            'purpose' => MarketingMessagePurpose::REVIEW_REQUEST,
            'status' => MarketingMessageStatus::SKIPPED,
            'skipped_reason' => $reason,
        ]);
    }

    /**
     * @return array{review_link: string, booking_link: string}
     */
    private function buildLinks(Tenant $tenant): array
    {
        $frontend = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');
        $slug = $tenant->slug ?: 'salon';

        return [
            'review_link' => "{$frontend}/book/{$slug}#reviews",
            'booking_link' => "{$frontend}/book/{$slug}",
        ];
    }
}
