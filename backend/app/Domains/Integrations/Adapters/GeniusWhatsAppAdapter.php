<?php

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\ProviderOutboundAdapterContract;
use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderAdapterResultDto;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Domains\Notifications\Services\WhatsApp\GeniusWhatsAppClient;
use App\Domains\Notifications\Services\WhatsApp\WhatsAppCredentialResolver;

/**
 * Genius WhatsApp adapter — tenant hosted session + platform API, else account/platform credentials.
 */
final class GeniusWhatsAppAdapter implements ProviderOutboundAdapterContract
{
    public function __construct(
        private readonly GeniusWhatsAppClient $client,
        private readonly WhatsAppCredentialResolver $credentials,
    ) {}

    public function driver(): string
    {
        return ProviderDriver::GENIUS;
    }

    public function supportsCategory(string $category): bool
    {
        return $category === ProviderCategory::SMS;
    }

    public function dispatch(ProviderAccount $account, OutboundProviderDispatchDto $dto): ProviderAdapterResultDto
    {
        $to = $dto->recipientPhone ?? $dto->recipientAddress;
        if ($to === null || trim($to) === '') {
            return ProviderAdapterResultDto::failed(
                'Genius WhatsApp requires a recipient phone number.',
                'genius_missing_recipient',
            );
        }

        $resolved = $this->credentials->resolve($dto->tenantId);
        $apiKey = (string) ($resolved['genius']['api_key'] ?? '');
        $sessionId = (string) ($resolved['genius']['session_id'] ?? '');
        $baseUrl = (string) ($resolved['genius']['base_url'] ?? '');

        // Explicit provider-account credentials can still override when present.
        $accountCreds = $account->credentials_json ?? [];
        if (! empty($accountCreds['api_key']) && ! empty($accountCreds['session_id'])) {
            $apiKey = (string) $accountCreds['api_key'];
            $sessionId = (string) $accountCreds['session_id'];
            $baseUrl = (string) ($accountCreds['base_url'] ?? $baseUrl);
        }

        if ($apiKey === '' || $sessionId === '') {
            return ProviderAdapterResultDto::failed(
                'Genius credentials incomplete (api_key/session_id).',
                'genius_credentials',
            );
        }

        $body = (string) ($dto->bodyText ?? $dto->subject ?? '');
        $result = $this->client->send($to, $body, [
            'api_key' => $apiKey,
            'session_id' => $sessionId,
            'base_url' => $baseUrl !== '' ? $baseUrl : config('whatsapp.genius.base_url'),
        ], [
            'type' => 'provider_dispatch',
            'purpose' => $dto->purpose,
            'source_id' => $dto->sourceId,
            'credential_source' => $resolved['source'],
        ]);

        if (! ($result['ok'] ?? false)) {
            return ProviderAdapterResultDto::failed(
                (string) ($result['error'] ?? 'Genius send failed'),
                'genius_http',
                [
                    'transport' => 'http',
                    'driver' => ProviderDriver::GENIUS,
                    'status' => $result['status'] ?? null,
                ],
            );
        }

        return ProviderAdapterResultDto::delivered(
            providerReference: 'genius:'.substr(md5($to.now()->timestamp), 0, 12),
            remoteStatus: 'sent',
            simulated: false,
            metadata: [
                'transport' => 'http',
                'driver' => ProviderDriver::GENIUS,
                'credential_source' => $resolved['source'],
            ],
        );
    }
}
