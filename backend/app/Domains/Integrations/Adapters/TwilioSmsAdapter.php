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
 * Twilio SMS adapter — live HTTP Messages API.
 */
final class TwilioSmsAdapter implements ProviderOutboundAdapterContract
{
    public function driver(): string
    {
        return ProviderDriver::TWILIO;
    }

    public function supportsCategory(string $category): bool
    {
        return $category === ProviderCategory::SMS;
    }

    public function dispatch(ProviderAccount $account, OutboundProviderDispatchDto $dto): ProviderAdapterResultDto
    {
        if ($this->shouldFail($dto)) {
            return ProviderAdapterResultDto::failed(
                'Twilio dispatch rejected recipient (simulated failure).',
                'twilio_simulated_failure',
                ['transport' => 'http', 'driver' => ProviderDriver::TWILIO],
            );
        }

        $to = $dto->recipientPhone ?? $dto->recipientAddress;
        if ($to === null || trim($to) === '') {
            return ProviderAdapterResultDto::failed(
                'Twilio dispatch requires a recipient phone number.',
                'twilio_missing_recipient',
            );
        }

        $credentials = $account->credentials_json ?? [];
        $sid = (string) ($credentials['account_sid'] ?? '');
        $token = (string) ($credentials['auth_token'] ?? '');
        $from = (string) ($credentials['from_number'] ?? $account->phone_number ?? '');

        if ($sid === '' || $token === '' || $from === '') {
            return ProviderAdapterResultDto::failed(
                'Twilio credentials incomplete (account_sid/auth_token/from_number).',
                'twilio_credentials',
            );
        }

        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Messages.json';

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(20)
                ->post($endpoint, [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $dto->bodyText ?? ($dto->subject ?? ''),
                ]);

            if (! $response->successful()) {
                return ProviderAdapterResultDto::failed(
                    'Twilio API error: HTTP '.$response->status().' '.$response->body(),
                    'twilio_http_'.$response->status(),
                    [
                        'transport' => 'http',
                        'driver' => ProviderDriver::TWILIO,
                        'status' => $response->status(),
                    ],
                );
            }

            $json = $response->json() ?? [];
            $reference = (string) ($json['sid'] ?? ('SM'.substr(md5($to.now()->timestamp), 0, 10)));

            return ProviderAdapterResultDto::delivered(
                providerReference: $reference,
                remoteStatus: (string) ($json['status'] ?? 'queued'),
                simulated: false,
                metadata: [
                    'transport' => 'http',
                    'driver' => ProviderDriver::TWILIO,
                    'from_number' => $from,
                ],
            );
        } catch (Throwable $e) {
            return ProviderAdapterResultDto::failed(
                'Twilio transport exception: '.$e->getMessage(),
                'twilio_exception',
                ['transport' => 'http', 'driver' => ProviderDriver::TWILIO],
            );
        }
    }

    private function shouldFail(OutboundProviderDispatchDto $dto): bool
    {
        if (($dto->metadata['simulate_failure'] ?? false) === true) {
            return true;
        }

        $phone = strtolower($dto->recipientPhone ?? $dto->recipientAddress ?? '');

        return $phone !== '' && str_contains($phone, 'fail');
    }
}
