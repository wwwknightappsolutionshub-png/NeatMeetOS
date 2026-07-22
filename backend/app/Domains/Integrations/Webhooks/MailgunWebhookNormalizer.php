<?php

namespace App\Domains\Integrations\Webhooks;

use App\Domains\Integrations\Enums\ProviderDriver;

final class MailgunWebhookNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     * @return array{event_type: string, external_event_id: ?string, fields: array<string, mixed>}
     */
    public function normalize(array $payload, ?array $headers = null): array
    {
        $eventData = $payload['event-data'] ?? $payload['event_data'] ?? [];

        return [
            'event_type' => (string) ($eventData['event'] ?? $payload['event'] ?? 'unknown'),
            'external_event_id' => isset($eventData['id']) ? (string) $eventData['id'] : ($payload['Message-Id'] ?? null),
            'fields' => [
                'driver' => ProviderDriver::MAILGUN,
                'recipient' => $eventData['recipient'] ?? null,
                'domain' => $eventData['domain'] ?? null,
                'message_id' => $eventData['message']['headers']['message-id'] ?? null,
            ],
        ];
    }
}
