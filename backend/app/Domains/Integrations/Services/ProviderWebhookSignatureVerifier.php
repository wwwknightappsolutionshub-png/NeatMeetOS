<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;

/**
 * Validates inbound provider webhook HMAC signatures (Stripe, Mailgun, Twilio).
 */
final class ProviderWebhookSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{valid: bool|null, reason: string|null, checked: bool}
     */
    public function verify(
        string $driver,
        string $rawBody,
        array $payload,
        array $headers,
        ?ProviderAccount $account,
    ): array {
        $secret = $this->resolveSecret($driver, $account);

        if ($secret === null || $secret === '') {
            return [
                'valid' => null,
                'reason' => 'no_webhook_secret_configured',
                'checked' => false,
            ];
        }

        $ok = match ($driver) {
            ProviderDriver::STRIPE => $this->verifyStripe($rawBody, $headers, $secret),
            ProviderDriver::MAILGUN => $this->verifyMailgun($payload, $headers, $secret),
            ProviderDriver::TWILIO => $this->verifyTwilio($rawBody, $headers, $secret, $account),
            default => false,
        };

        return [
            'valid' => $ok,
            'reason' => $ok ? null : 'signature_mismatch',
            'checked' => true,
        ];
    }

    private function resolveSecret(string $driver, ?ProviderAccount $account): ?string
    {
        if ($account === null) {
            return null;
        }

        if (! empty($account->webhook_secret)) {
            return (string) $account->webhook_secret;
        }

        $credentials = $account->credentials_json ?? [];

        return match ($driver) {
            ProviderDriver::STRIPE => isset($credentials['webhook_secret'])
                ? (string) $credentials['webhook_secret']
                : null,
            ProviderDriver::MAILGUN => isset($credentials['webhook_signing_key'])
                ? (string) $credentials['webhook_signing_key']
                : (isset($credentials['api_key']) ? (string) $credentials['api_key'] : null),
            ProviderDriver::TWILIO => isset($credentials['auth_token'])
                ? (string) $credentials['auth_token']
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function verifyStripe(string $rawBody, array $headers, string $secret): bool
    {
        $header = $this->header($headers, 'Stripe-Signature')
            ?? $this->header($headers, 'stripe-signature');

        if ($header === null || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $tolerance = (int) config('integrations.webhooks.stripe_tolerance_seconds', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    private function verifyMailgun(array $payload, array $headers, string $secret): bool
    {
        $signature = $payload['signature'] ?? null;

        if (is_array($signature)) {
            $timestamp = (string) ($signature['timestamp'] ?? '');
            $token = (string) ($signature['token'] ?? '');
            $sig = (string) ($signature['signature'] ?? '');
        } else {
            $timestamp = (string) ($payload['timestamp'] ?? $this->header($headers, 'X-Mailgun-Timestamp') ?? '');
            $token = (string) ($payload['token'] ?? $this->header($headers, 'X-Mailgun-Token') ?? '');
            $sig = (string) ($signature ?? $this->header($headers, 'X-Mailgun-Signature') ?? '');
        }

        if ($timestamp === '' || $token === '' || $sig === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.$token, $secret);

        return hash_equals($expected, $sig);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function verifyTwilio(string $rawBody, array $headers, string $secret, ?ProviderAccount $account): bool
    {
        $signature = $this->header($headers, 'X-Twilio-Signature')
            ?? $this->header($headers, 'x-twilio-signature');

        if ($signature === null || $signature === '') {
            return false;
        }

        $configuredUrl = $account?->configuration_json['webhook_url'] ?? null;
        $url = $this->header($headers, 'X-Twilio-Request-Url');
        if ($url === null || $url === '') {
            $url = is_string($configuredUrl) && $configuredUrl !== ''
                ? $configuredUrl
                : rtrim((string) config('app.url'), '/').'/api/v1/integrations/webhooks/twilio';
        }

        parse_str($rawBody, $params);
        if ($params === [] && $rawBody !== '' && str_starts_with(ltrim($rawBody), '{')) {
            $decoded = json_decode($rawBody, true);
            $params = is_array($decoded) ? $decoded : [];
        }

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return null;
    }
}
