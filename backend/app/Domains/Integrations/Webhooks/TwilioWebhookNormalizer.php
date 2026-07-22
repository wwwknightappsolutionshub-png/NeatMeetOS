<?php

namespace App\Domains\Integrations\Webhooks;

use App\Domains\Integrations\Enums\ProviderDriver;

final class TwilioWebhookNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     * @return array{event_type: string, external_event_id: ?string, fields: array<string, mixed>}
     */
    public function normalize(array $payload, ?array $headers = null): array
    {
        $status = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? 'unknown';

        return [
            'event_type' => (string) $status,
            'external_event_id' => isset($payload['MessageSid']) ? (string) $payload['MessageSid'] : null,
            'fields' => [
                'driver' => ProviderDriver::TWILIO,
                'from' => $payload['From'] ?? null,
                'to' => $payload['To'] ?? null,
                'error_code' => $payload['ErrorCode'] ?? null,
            ],
        ];
    }
}
