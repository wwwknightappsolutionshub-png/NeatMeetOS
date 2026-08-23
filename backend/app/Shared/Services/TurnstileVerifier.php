<?php

namespace App\Shared\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        $explicit = config('security.turnstile.enabled');
        if ($explicit === false || $explicit === 'false' || $explicit === '0' || $explicit === 0) {
            return false;
        }

        $secret = trim((string) config('security.turnstile.secret_key', ''));
        if ($secret === '') {
            return false;
        }

        if ($explicit === true || $explicit === 'true' || $explicit === '1' || $explicit === 1) {
            return true;
        }

        // Default: enabled whenever a secret is configured.
        return true;
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            if (app()->environment('local', 'testing') === false
                && trim((string) config('security.turnstile.secret_key', '')) === '') {
                Log::warning('security.turnstile_skipped_missing_secret');
            }

            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $secret = (string) config('security.turnstile.secret_key');
        $url = (string) config('security.turnstile.verify_url');

        try {
            $payload = [
                'secret' => $secret,
                'response' => $token,
            ];
            if ($remoteIp !== null && $remoteIp !== '') {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()
                ->timeout(8)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('security.turnstile_http_failed', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $body = $response->json();

            return (bool) ($body['success'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('security.turnstile_exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
