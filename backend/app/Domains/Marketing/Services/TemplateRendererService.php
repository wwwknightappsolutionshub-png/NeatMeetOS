<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Enums\MarketingChannel;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Memberships\Models\ClientMembership;
use App\Shared\Tenancy\TenantContext;

/**
 * Renders marketing templates by substituting {{placeholder}} tokens.
 *
 * Placeholders resolve from a flat payload keyed with dotted paths such as
 * "client.first_name". Unknown tokens render as an empty string so partial
 * payloads never leak raw template syntax to recipients.
 *
 * Email HTML is wrapped with MarketingEmailLayoutService chrome at render time.
 */
class TemplateRendererService
{
    public function __construct(
        private readonly MarketingEmailLayoutService $emailLayout,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array<int, string>
     */
    public function supportedPlaceholders(): array
    {
        return [
            'client.first_name',
            'client.last_name',
            'business.name',
            'location.name',
            'appointment.start_at',
            'appointment.service_summary',
            'membership.plan_name',
            'review.link',
            'booking.link',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function samplePayload(): array
    {
        return [
            'client.first_name' => 'Alex',
            'client.last_name' => 'Morgan',
            'business.name' => 'Your Business',
            'location.name' => 'Main Studio',
            'appointment.start_at' => now()->addDay()->format('D, j M Y g:i A'),
            'appointment.service_summary' => 'Signature Cut & Style',
            'membership.plan_name' => 'Gold Membership',
            'review.link' => 'https://example.com/review/sample',
            'booking.link' => 'https://example.com/book/sample',
        ];
    }

    /**
     * Render a raw string against a flat payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(?string $body, array $payload): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($payload) {
            $key = $matches[1];

            return array_key_exists($key, $payload) && $payload[$key] !== null
                ? (string) $payload[$key]
                : '';
        }, $body) ?? $body;
    }

    /**
     * Render a full template (subject + bodies) against a payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{subject: string|null, body_text: string, body_html: string|null, template_snapshot: array<string, mixed>, variables_snapshot: array<string, mixed>}
     */
    public function renderTemplate(MarketingTemplate $template, array $payload, ?Tenant $tenant = null): array
    {
        $bodyHtml = $template->body_html !== null ? $this->render($template->body_html, $payload) : null;

        if ($bodyHtml !== null
            && $bodyHtml !== ''
            && $template->channel === MarketingChannel::EMAIL
        ) {
            $tenant ??= $this->tenantContext->get();
            if ($tenant !== null) {
                $bodyHtml = $this->emailLayout->wrap($tenant, $bodyHtml);
            }
        }

        return [
            'subject' => $template->subject !== null ? $this->render($template->subject, $payload) : null,
            'body_text' => $this->render($template->body_text, $payload),
            'body_html' => $bodyHtml,
            'template_snapshot' => [
                'template_id' => $template->id,
                'name' => $template->name,
                'channel' => $template->channel,
                'category' => $template->category,
                'subject' => $template->subject,
                'body_text' => $template->body_text,
                'body_html' => $template->body_html,
            ],
            'variables_snapshot' => $payload,
        ];
    }

    /**
     * Build a flat placeholder payload from domain models.
     *
     * @param  array{review_link?: string|null, booking_link?: string|null}  $links
     * @return array<string, string|null>
     */
    public function buildPayload(
        Client $client,
        ?Tenant $tenant = null,
        ?Location $location = null,
        ?Appointment $appointment = null,
        ?ClientMembership $membership = null,
        array $links = [],
    ): array {
        $payload = $this->samplePayload();

        $payload['client.first_name'] = $client->first_name ?? '';
        $payload['client.last_name'] = $client->last_name ?? '';

        $payload['business.name'] = $tenant?->trading_name
            ?? $tenant?->name
            ?? $payload['business.name'];

        $branding = $tenant?->getBranding() ?? [];
        $brandDisplay = trim((string) ($branding['brand_display_name'] ?? ''));
        if ($brandDisplay !== '') {
            $payload['business.name'] = $brandDisplay;
        }

        $payload['location.name'] = $location?->name
            ?? $client->primaryLocation?->name
            ?? '';

        if ($appointment !== null) {
            $payload['appointment.start_at'] = $appointment->starts_at?->format('D, j M Y g:i A') ?? '';
            $payload['appointment.service_summary'] = $this->serviceSummary($appointment);
        } else {
            $payload['appointment.start_at'] = '';
            $payload['appointment.service_summary'] = '';
        }

        $payload['membership.plan_name'] = $membership?->membershipPlan?->name ?? '';
        $payload['review.link'] = $links['review_link'] ?? '';
        $payload['booking.link'] = $links['booking_link'] ?? '';

        return $payload;
    }

    private function serviceSummary(Appointment $appointment): string
    {
        $lines = $appointment->relationLoaded('serviceLines')
            ? $appointment->serviceLines
            : $appointment->serviceLines()->get();

        $names = $lines
            ->pluck('service_name')
            ->filter()
            ->values()
            ->all();

        return implode(', ', $names);
    }
}
