<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;

final class ProviderCredentialValidator
{
    public function __construct(
        private readonly ProviderDriverCompatibility $compatibility,
    ) {}

    /**
     * @return array{valid: bool, missing: array<int, string>, summary: array<string, mixed>}
     */
    public function validate(ProviderAccount $account): array
    {
        if (in_array($account->driver, [ProviderDriver::SIMULATION, ProviderDriver::MANUAL], true)) {
            return ['valid' => true, 'missing' => [], 'summary' => ['mode' => 'simulation']];
        }

        if (! $this->compatibility->isCompatible($account->category, $account->driver)) {
            return [
                'valid' => false,
                'missing' => ['category_driver_mismatch'],
                'summary' => ['mode' => 'invalid', 'reason' => 'category_driver_mismatch'],
            ];
        }

        $credentials = $account->credentials_json ?? [];
        $required = $this->requiredFields($account->driver);
        $missing = [];

        foreach ($required as $field) {
            if ($field === 'from_number') {
                if (empty($credentials['from_number']) && empty($account->phone_number)) {
                    $missing[] = $field;
                }

                continue;
            }

            if (empty($credentials[$field])) {
                $missing[] = $field;
            }
        }

        return [
            'valid' => $missing === [],
            'missing' => $missing,
            'summary' => $this->configSummary($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function configSummary(ProviderAccount $account): array
    {
        $credentials = $account->credentials_json ?? [];
        $configuration = $account->configuration_json ?? [];

        return match ($account->driver) {
            ProviderDriver::MAILGUN => [
                'driver' => $account->driver,
                'domain' => $credentials['domain'] ?? $configuration['domain'] ?? null,
                'has_api_key' => ! empty($credentials['api_key']),
                'from_address' => $account->from_address,
            ],
            ProviderDriver::TWILIO => [
                'driver' => $account->driver,
                'has_account_sid' => ! empty($credentials['account_sid']),
                'has_auth_token' => ! empty($credentials['auth_token']),
                'from_number' => $credentials['from_number'] ?? $account->phone_number,
            ],
            ProviderDriver::STRIPE => [
                'driver' => $account->driver,
                'has_secret_key' => ! empty($credentials['secret_key']),
                'has_publishable_key' => ! empty($credentials['publishable_key']),
                'has_webhook_secret' => ! empty($account->webhook_secret),
            ],
            default => [
                'driver' => $account->driver,
                'has_credentials' => ! empty($credentials),
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function requiredFields(string $driver): array
    {
        return match ($driver) {
            ProviderDriver::MAILGUN => ['api_key', 'domain'],
            ProviderDriver::TWILIO => ['account_sid', 'auth_token', 'from_number'],
            ProviderDriver::STRIPE => ['secret_key'],
            default => [],
        };
    }
}
