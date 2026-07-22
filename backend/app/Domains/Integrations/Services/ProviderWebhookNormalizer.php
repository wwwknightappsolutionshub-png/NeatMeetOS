<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Webhooks\MailgunWebhookNormalizer;
use App\Domains\Integrations\Webhooks\StripeWebhookNormalizer;
use App\Domains\Integrations\Webhooks\TwilioWebhookNormalizer;

final class ProviderWebhookNormalizer
{
    public function __construct(
        private readonly StripeWebhookNormalizer $stripe,
        private readonly MailgunWebhookNormalizer $mailgun,
        private readonly TwilioWebhookNormalizer $twilio,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     * @return array{event_type: string, external_event_id: ?string, fields: array<string, mixed>, metadata: array<string, mixed>}
     */
    public function normalize(string $driver, array $payload, ?array $headers = null): array
    {
        $normalized = match ($driver) {
            ProviderDriver::STRIPE => $this->stripe->normalize($payload, $headers),
            ProviderDriver::MAILGUN => $this->mailgun->normalize($payload, $headers),
            ProviderDriver::TWILIO => $this->twilio->normalize($payload, $headers),
            default => [
                'event_type' => (string) ($payload['type'] ?? $payload['event_type'] ?? 'unknown'),
                'external_event_id' => isset($payload['id']) ? (string) $payload['id'] : null,
                'fields' => ['driver' => $driver],
            ],
        };

        return array_merge($normalized, [
            'metadata' => [
                'normalized' => $normalized['fields'],
                'signature_check' => 'deferred',
            ],
        ]);
    }
}
