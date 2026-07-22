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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WinBackAutomationService
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
        $inactivityDays = (int) ($settings->win_back_inactivity_days ?? 120);
        $runSource = $options['run_source'] ?? MarketingRunSource::SCHEDULER;

        $campaigns = MarketingCampaign::query()
            ->where('campaign_type', MarketingCampaignType::AUTOMATION)
            ->where('trigger_type', MarketingTriggerType::WIN_BACK)
            ->where('status', MarketingCampaignStatus::ACTIVE)
            ->with('template')
            ->get();

        $runs = [];

        foreach ($campaigns as $campaign) {
            $runs[] = $this->generateForCampaign($campaign, $inactivityDays, $runSource, $options);
        }

        return $runs;
    }

    private function generateForCampaign(
        MarketingCampaign $campaign,
        int $inactivityDays,
        string $runSource,
        array $options,
    ): MarketingRun {
        return DB::transaction(function () use ($campaign, $inactivityDays, $runSource, $options) {
            $tenantId = $this->scope->tenantId();
            $tenant = Tenant::query()->findOrFail($tenantId);
            $links = $this->buildLinks($tenant);
            $cutoff = now()->subDays($inactivityDays);

            $run = MarketingRun::query()->create([
                'tenant_id' => $tenantId,
                'marketing_campaign_id' => $campaign->id,
                'trigger_type' => MarketingTriggerType::WIN_BACK,
                'run_source' => $runSource,
                'status' => MarketingRunStatus::PROCESSING,
                'filters_json' => [
                    'inactivity_days' => $inactivityDays,
                    'location_id' => $campaign->location_id,
                ],
                'started_at' => now(),
                'created_by_team_member_id' => $options['created_by_team_member_id'] ?? null,
            ]);

            $futureClientIds = $this->clientsWithFutureBooking();
            $lastVisits = $this->lastCompletedVisitByClient($campaign->location_id);
            $eligible = 0;
            $skipped = 0;
            $byReason = [];

            foreach ($lastVisits as $clientId => $lastVisitAt) {
                if (in_array($clientId, $futureClientIds, true)) {
                    continue;
                }

                if (Carbon::parse($lastVisitAt)->greaterThan($cutoff)) {
                    continue;
                }

                if ($this->alreadyQueued($clientId)) {
                    continue;
                }

                $client = Client::query()->with('primaryLocation')->find($clientId);
                if ($client === null) {
                    continue;
                }

                $context = ['location_ids' => $campaign->location_id ? [$campaign->location_id] : []];
                $check = $this->eligibility->evaluate($client, $campaign->channel, $context);

                if (! $check['eligible']) {
                    $this->createSkippedMessage($run, $campaign, $client, $check['skipped_reason']);
                    $skipped++;
                    $byReason[$check['skipped_reason']] = ($byReason[$check['skipped_reason']] ?? 0) + 1;

                    continue;
                }

                $template = $campaign->template
                    ?? $this->templateService->resolveForTrigger(
                        MarketingTriggerType::WIN_BACK,
                        $campaign->channel,
                        $campaign->template_id,
                    );

                if ($template === null) {
                    $this->createSkippedMessage($run, $campaign, $client, 'missing_template');
                    $skipped++;
                    $byReason['missing_template'] = ($byReason['missing_template'] ?? 0) + 1;

                    continue;
                }

                $payload = $this->renderer->buildPayload($client, $tenant, $client->primaryLocation, null, null, $links);
                $rendered = $this->renderer->renderTemplate($template, $payload);

                MarketingMessage::query()->create([
                    'tenant_id' => $tenantId,
                    'marketing_campaign_id' => $campaign->id,
                    'marketing_run_id' => $run->id,
                    'client_id' => $client->id,
                    'location_id' => $client->primary_location_id,
                    'channel' => $campaign->channel,
                    'purpose' => MarketingMessagePurpose::WIN_BACK,
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
                'clients_matched' => count($lastVisits),
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

    /**
     * @return array<int, string>
     */
    private function clientsWithFutureBooking(): array
    {
        return Appointment::query()
            ->whereNotNull('client_id')
            ->where('starts_at', '>', now())
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
            ])
            ->distinct()
            ->pluck('client_id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function lastCompletedVisitByClient(?string $locationId): array
    {
        return Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereNotNull('client_id')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('client_id, MAX(COALESCE(ends_at, starts_at)) as last_visit')
            ->groupBy('client_id')
            ->pluck('last_visit', 'client_id')
            ->all();
    }

    private function alreadyQueued(string $clientId): bool
    {
        return MarketingMessage::query()
            ->where('client_id', $clientId)
            ->where('purpose', MarketingMessagePurpose::WIN_BACK)
            ->where('created_at', '>=', now()->subDays(14))
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
        ?string $reason,
    ): void {
        MarketingMessage::query()->create([
            'tenant_id' => $run->tenant_id,
            'marketing_campaign_id' => $campaign->id,
            'marketing_run_id' => $run->id,
            'client_id' => $client->id,
            'location_id' => $client->primary_location_id,
            'channel' => $campaign->channel,
            'purpose' => MarketingMessagePurpose::WIN_BACK,
            'status' => MarketingMessageStatus::SKIPPED,
            'skipped_reason' => $reason,
        ]);
    }

    /**
     * @return array{review_link: string, booking_link: string}
     */
    private function buildLinks(Tenant $tenant): array
    {
        $base = $tenant->slug ? "https://{$tenant->slug}.neatmeet.app" : 'https://neatmeet.app';

        return [
            'review_link' => "{$base}/review",
            'booking_link' => "{$base}/book",
        ];
    }
}
