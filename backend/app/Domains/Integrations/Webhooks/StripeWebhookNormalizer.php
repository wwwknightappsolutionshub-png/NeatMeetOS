<?php

namespace App\Domains\Integrations\Webhooks;

use App\Domains\Integrations\Enums\ProviderDriver;

final class StripeWebhookNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     * @return array{event_type: string, external_event_id: ?string, fields: array<string, mixed>}
     */
    public function normalize(array $payload, ?array $headers = null): array
    {
        $object = $payload['data']['object'] ?? [];

        return [
            'event_type' => (string) ($payload['type'] ?? $payload['event_type'] ?? 'unknown'),
            'external_event_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'fields' => [
                'driver' => ProviderDriver::STRIPE,
                'object_type' => $object['object'] ?? null,
                'object_id' => $object['id'] ?? null,
                'livemode' => $payload['livemode'] ?? null,
            ],
        ];
    }
}
