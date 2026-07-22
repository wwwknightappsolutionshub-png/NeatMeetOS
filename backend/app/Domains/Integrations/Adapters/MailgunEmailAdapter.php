<?php

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\ProviderOutboundAdapterContract;
use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderAdapterResultDto;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mailgun email adapter — live HTTP Messages API.
 */
final class MailgunEmailAdapter implements ProviderOutboundAdapterContract
{
    public function driver(): string
    {
        return ProviderDriver::MAILGUN;
    }

    public function supportsCategory(string $category): bool
    {
        return $category === ProviderCategory::EMAIL;
    }

    public function dispatch(ProviderAccount $account, OutboundProviderDispatchDto $dto): ProviderAdapterResultDto
    {
        if ($this->shouldFail($dto)) {
            return ProviderAdapterResultDto::failed(
                'Mailgun dispatch rejected recipient (simulated failure).',
                'mailgun_simulated_failure',
                ['transport' => 'http', 'driver' => ProviderDriver::MAILGUN],
            );
        }

        $to = $dto->recipientAddress;
        if ($to === null || trim($to) === '') {
            return ProviderAdapterResultDto::failed(
                'Mailgun dispatch requires a recipient email address.',
                'mailgun_missing_recipient',
            );
        }

        $credentials = $account->credentials_json ?? [];
        $domain = (string) ($credentials['domain'] ?? $account->configuration_json['domain'] ?? '');
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $from = $account->from_address
            ?? ($credentials['from_address'] ?? null)
            ?? config('mail.from.address');

        if ($domain === '' || $apiKey === '') {
            return ProviderAdapterResultDto::failed(
                'Mailgun credentials incomplete (domain/api_key).',
                'mailgun_credentials',
            );
        }

        $baseUrl = rtrim((string) ($credentials['base_url'] ?? 'https://api.mailgun.net'), '/');
        $endpoint = $baseUrl.'/v3/'.$domain.'/messages';

        $form = [
            'from' => $from,
            'to' => $to,
            'subject' => $dto->subject ?? 'Notification',
            'text' => $dto->bodyText ?? '',
        ];

        if (! empty($dto->payload['body_html'])) {
            $form['html'] = (string) $dto->payload['body_html'];
        }

        try {
            $response = Http::withBasicAuth('api', $apiKey)
                ->asForm()
                ->timeout(20)
                ->post($endpoint, $form);

            if (! $response->successful()) {
                return ProviderAdapterResultDto::failed(
                    'Mailgun API error: HTTP '.$response->status().' '.$response->body(),
                    'mailgun_http_'.$response->status(),
                    [
                        'transport' => 'http',
                        'driver' => ProviderDriver::MAILGUN,
                        'status' => $response->status(),
                    ],
                );
            }

            $json = $response->json() ?? [];
            $reference = (string) ($json['id'] ?? ('mg_'.substr(md5($to.now()->timestamp), 0, 16)));

            return ProviderAdapterResultDto::delivered(
                providerReference: $reference,
                remoteStatus: (string) ($json['message'] ?? 'queued'),
                simulated: false,
                metadata: [
                    'transport' => 'http',
                    'driver' => ProviderDriver::MAILGUN,
                    'domain' => $domain,
                ],
            );
        } catch (Throwable $e) {
            return ProviderAdapterResultDto::failed(
                'Mailgun transport exception: '.$e->getMessage(),
                'mailgun_exception',
                ['transport' => 'http', 'driver' => ProviderDriver::MAILGUN],
            );
        }
    }

    private function shouldFail(OutboundProviderDispatchDto $dto): bool
    {
        if (($dto->metadata['simulate_failure'] ?? false) === true) {
            return true;
        }

        $address = strtolower($dto->recipientAddress ?? '');

        return $address !== '' && str_contains($address, 'fail');
    }
}
