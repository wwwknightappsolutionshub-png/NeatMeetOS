<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Enums\MarketingMessagePurpose;
use App\Domains\Marketing\Enums\MarketingMessageStatus;
use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Memberships\Models\ClientMembership;
use App\Shared\Tenancy\TenantContext;

/**
 * Creates and optionally dispatches cadence messages from named starter templates.
 */
class MarketingCadenceDispatcher
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
        private readonly MarketingEligibilityService $eligibility,
        private readonly TemplateRendererService $renderer,
        private readonly MarketingStarterTemplateService $starters,
        private readonly MarketingDeliveryService $delivery,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return MarketingMessage|null Null when skipped (ineligible, missing template, already queued).
     */
    public function queueNamedTemplate(
        Client $client,
        string $templateName,
        string $channel,
        string $purpose,
        int $cooldownDays,
        bool $dispatchImmediately = true,
        ?ClientMembership $membership = null,
        ?\DateTimeInterface $scheduledFor = null,
    ): ?MarketingMessage {
        $this->scope->assertTenantModel($client);

        if ($this->alreadyQueued($client->id, $purpose, $channel, $cooldownDays)) {
            return null;
        }

        $template = $this->starters->findByName($templateName);
        if ($template === null || $template->channel !== $channel || ! $template->is_active) {
            $template = MarketingTemplate::query()
                ->where('channel', $channel)
                ->where('is_active', true)
                ->where('name', $templateName)
                ->first();
        }

        if ($template === null) {
            return null;
        }

        $check = $this->eligibility->evaluate($client, $channel);
        if (! $check['eligible']) {
            return null;
        }

        $tenant = $this->tenantContext->get() ?? Tenant::query()->find($client->tenant_id);
        if ($tenant === null) {
            return null;
        }

        if ($membership !== null && ! $membership->relationLoaded('membershipPlan')) {
            $membership->load('membershipPlan');
        }

        $client->loadMissing('primaryLocation');
        $links = $this->buildLinks($tenant);
        $payload = $this->renderer->buildPayload(
            $client,
            $tenant,
            $client->primaryLocation,
            null,
            $membership,
            $links,
        );
        $rendered = $this->renderer->renderTemplate($template, $payload, $tenant);

        $message = MarketingMessage::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'location_id' => $client->primary_location_id,
            'membership_id' => $membership?->id,
            'channel' => $channel,
            'purpose' => $purpose,
            'status' => MarketingMessageStatus::PENDING,
            'recipient_address' => $check['recipient_address'],
            'subject' => $rendered['subject'],
            'rendered_body_text' => $rendered['body_text'],
            'rendered_body_html' => $rendered['body_html'],
            'template_snapshot_json' => $rendered['template_snapshot'],
            'variables_snapshot_json' => $rendered['variables_snapshot'],
            'scheduled_for' => $scheduledFor ?? now(),
        ]);

        if ($dispatchImmediately && ($scheduledFor === null || $scheduledFor <= now())) {
            return $this->delivery->dispatchMessage($message);
        }

        return $message->fresh();
    }

    public function alreadyQueued(string $clientId, string $purpose, string $channel, int $cooldownDays): bool
    {
        return MarketingMessage::query()
            ->where('client_id', $clientId)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subDays(max(1, $cooldownDays)))
            ->whereIn('status', [
                MarketingMessageStatus::PENDING,
                MarketingMessageStatus::PROCESSING,
                MarketingMessageStatus::SENT,
                MarketingMessageStatus::DELIVERED,
            ])
            ->exists();
    }

    /**
     * @return array{review_link: string, booking_link: string}
     */
    public function buildLinks(Tenant $tenant): array
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $slug = $tenant->slug ?? '';

        if ($frontend !== '' && $slug !== '') {
            return [
                'review_link' => "{$frontend}/book/{$slug}",
                'booking_link' => "{$frontend}/book/{$slug}",
            ];
        }

        $base = $slug !== '' ? "https://{$slug}.neatmeet.app" : 'https://neatmeet.app';

        return [
            'review_link' => "{$base}/review",
            'booking_link' => "{$base}/book",
        ];
    }
}
