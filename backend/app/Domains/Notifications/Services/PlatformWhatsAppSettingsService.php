<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Domains\Notifications\Services\WhatsApp\GeniusWhatsAppClient;
use App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService;
use App\Domains\Notifications\Services\WhatsApp\WhatsAppCredentialResolver;
use App\Domains\Notifications\Services\WhatsApp\WhatsAppQueueFlushService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class PlatformWhatsAppSettingsService
{
    public const DEFAULT_TEST_MESSAGE = 'NeatMeet OS platform WhatsApp test — if you received this, Genius delivery works.';

    public function __construct(
        private readonly GeniusWhatsAppClient $genius,
        private readonly AuditLogger $auditLogger,
        private readonly WhatsAppCredentialResolver $credentials,
        private readonly WhatsAppQueueFlushService $queueFlush,
        private readonly TenantContext $tenantContext,
        private readonly PlatformSignupWhatsAppWelcomeService $signupWelcome,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->serialize($this->getOrCreate());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        $settings = $this->getOrCreate();
        $payload = [];

        if (array_key_exists('enabled', $data)) {
            $payload['enabled'] = (bool) $data['enabled'];
        }

        if (array_key_exists('provider', $data)) {
            $provider = strtolower(trim((string) $data['provider']));
            if (! in_array($provider, PlatformWhatsAppSettings::providers(), true)) {
                throw ValidationException::withMessages([
                    'provider' => ['Provider must be genius, meta, or twilio.'],
                ]);
            }
            $payload['provider'] = $provider;
        }

        if (array_key_exists('api_key', $data) && filled($data['api_key'])) {
            $payload['api_key'] = (string) $data['api_key'];
        }

        if (array_key_exists('session_id', $data)) {
            $payload['session_id'] = $this->nullableString($data['session_id']);
        }

        if (array_key_exists('base_url', $data)) {
            $payload['base_url'] = $this->nullableString($data['base_url'])
                ?: (string) config('whatsapp.genius.base_url');
        }

        if (array_key_exists('meta_phone_number_id', $data)) {
            $payload['meta_phone_number_id'] = $this->nullableString($data['meta_phone_number_id']);
        }

        if (array_key_exists('meta_access_token', $data) && filled($data['meta_access_token'])) {
            $payload['meta_access_token'] = (string) $data['meta_access_token'];
        }

        if (array_key_exists('twilio_account_sid', $data)) {
            $payload['twilio_account_sid'] = $this->nullableString($data['twilio_account_sid']);
        }

        if (array_key_exists('twilio_auth_token', $data) && filled($data['twilio_auth_token'])) {
            $payload['twilio_auth_token'] = (string) $data['twilio_auth_token'];
        }

        if (array_key_exists('twilio_from', $data)) {
            $payload['twilio_from'] = $this->nullableString($data['twilio_from']);
        }

        $welcomeFields = array_intersect_key($data, array_flip([
            'signup_welcome_enabled',
            'signup_welcome_trial_body',
            'signup_welcome_activation_body',
        ]));
        if ($welcomeFields !== []) {
            $this->signupWelcome->updateWelcome($welcomeFields);
            $settings = $settings->fresh() ?? $settings;
        }

        if ($payload === [] && $welcomeFields === []) {
            throw ValidationException::withMessages([
                'settings' => ['No WhatsApp settings provided.'],
            ]);
        }

        if ($payload !== []) {
            $settings->update($payload);
        }

        $audit = $payload;
        unset($audit['api_key'], $audit['meta_access_token'], $audit['twilio_auth_token']);
        if (isset($payload['api_key'])) {
            $audit['api_key_updated'] = true;
        }
        if (isset($payload['meta_access_token'])) {
            $audit['meta_access_token_updated'] = true;
        }
        if (isset($payload['twilio_auth_token'])) {
            $audit['twilio_auth_token_updated'] = true;
        }
        if ($welcomeFields !== []) {
            $audit['signup_welcome_updated'] = true;
        }

        $this->auditLogger->log('platform.whatsapp_settings.updated', $settings, null, $audit);

        return $this->serialize($settings->fresh());
    }

    public function getOrCreate(): PlatformWhatsAppSettings
    {
        $settings = PlatformWhatsAppSettings::query()->orderBy('created_at')->first();
        if ($settings) {
            return $settings;
        }

        return PlatformWhatsAppSettings::query()->create([
            'enabled' => false,
            'provider' => PlatformWhatsAppSettings::PROVIDER_GENIUS,
            'base_url' => config('whatsapp.genius.base_url'),
            'api_key' => config('whatsapp.genius.api_key'),
            'session_id' => config('whatsapp.genius.session_id'),
            'signup_welcome_enabled' => true,
        ]);
    }

    public function isGeniusReady(): bool
    {
        return $this->credentials->hasSendableCredentials($this->tenantContext->id());
    }

    /**
     * @return array{api_key: ?string, session_id: ?string, base_url: string}
     */
    public function geniusCredentials(?PlatformWhatsAppSettings $settings = null): array
    {
        $settings ??= $this->getOrCreate();

        return [
            'api_key' => $settings->api_key ?: config('whatsapp.genius.api_key'),
            'session_id' => $settings->session_id ?: config('whatsapp.genius.session_id'),
            'base_url' => rtrim((string) ($settings->base_url ?: config('whatsapp.genius.base_url')), '/'),
        ];
    }

    /**
     * Send operational WhatsApp using tenant hosted session when active, else platform config.
     *
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, error?: string, status?: int, provider: string, source?: string}
     */
    public function sendOperational(string $toPhone, string $message, array $context = []): array
    {
        $tenantId = $context['tenant_id'] ?? $this->tenantContext->id();
        $resolved = $this->credentials->resolve(is_string($tenantId) ? $tenantId : null);

        if (! $resolved['ready']) {
            return [
                'ok' => false,
                'provider' => 'genius',
                'source' => $resolved['source'],
                'error' => 'WhatsApp is not configured. Enable platform Genius, or connect a tenant scanned session.',
            ];
        }

        $result = $this->genius->send($toPhone, $message, $resolved['genius'], $context);

        return array_merge($result, [
            'provider' => 'genius',
            'source' => $resolved['source'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function queueStatus(?int $olderThanHours = 1): array
    {
        return $this->queueFlush->status($olderThanHours);
    }

    /**
     * @return array<string, mixed>
     */
    public function purgeStale(bool $includeFailedJobs = true, bool $includeStaleMessages = true, ?int $olderThanHours = 1): array
    {
        $result = $this->queueFlush->flush($includeFailedJobs, $includeStaleMessages, $olderThanHours);
        $this->auditLogger->log('platform.whatsapp_queue.flushed', $this->getOrCreate(), null, [
            'deleted_jobs' => $result['deleted_jobs'],
            'deleted_failed_jobs' => $result['deleted_failed_jobs'],
            'cancelled_messages' => $result['cancelled_messages'],
            'older_than_hours' => $result['older_than_hours'],
        ]);

        return $result;
    }

    /**
     * @return array{sent: bool, phone: string, provider: string, message: string, error?: string}
     */
    public function sendTestMessage(string $phone, ?string $message = null): array
    {
        $normalized = preg_replace('/\s+/', '', trim($phone)) ?? '';
        if ($normalized === '' || ! str_starts_with($normalized, '+')) {
            throw ValidationException::withMessages([
                'phone' => ['Phone must be E.164, e.g. +447700900123.'],
            ]);
        }

        $settings = $this->getOrCreate();
        if ($settings->provider !== PlatformWhatsAppSettings::PROVIDER_GENIUS) {
            throw ValidationException::withMessages([
                'provider' => ['Live test currently supports the Genius provider. Switch provider to genius.'],
            ]);
        }

        $creds = $this->geniusCredentials($settings);
        if (! filled($creds['api_key']) || ! filled($creds['session_id'])) {
            throw ValidationException::withMessages([
                'phone' => ['Save Genius API key and session ID before sending a test.'],
            ]);
        }

        $body = filled($message) ? trim((string) $message) : self::DEFAULT_TEST_MESSAGE;
        $result = $this->genius->send($normalized, $body, $creds, [
            'type' => 'platform_test',
        ]);

        $this->auditLogger->log('platform.whatsapp_settings.test_sent', $settings, null, [
            'phone' => $normalized,
            'ok' => (bool) ($result['ok'] ?? false),
        ]);

        if (! ($result['ok'] ?? false)) {
            return [
                'sent' => false,
                'phone' => $normalized,
                'provider' => 'genius',
                'message' => $body,
                'error' => (string) ($result['error'] ?? 'Send failed'),
            ];
        }

        return [
            'sent' => true,
            'phone' => $normalized,
            'provider' => 'genius',
            'message' => $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PlatformWhatsAppSettings $settings): array
    {
        $creds = $this->geniusCredentials($settings);

        return [
            'enabled' => (bool) $settings->enabled,
            'provider' => $settings->provider,
            'active_provider' => $settings->provider,
            'has_api_key' => filled($creds['api_key']),
            'session_id' => $settings->session_id,
            'base_url' => $creds['base_url'],
            'meta_phone_number_id' => $settings->meta_phone_number_id,
            'has_meta_access_token' => filled($settings->meta_access_token),
            'twilio_account_sid' => $settings->twilio_account_sid,
            'has_twilio_auth_token' => filled($settings->twilio_auth_token),
            'twilio_from' => $settings->twilio_from,
            'configured' => $this->isConfigured($settings),
            'queue' => $this->queueFlush->status(1),
            'signup_welcome' => $this->signupWelcome->serializeWelcome($settings),
            'providers' => [
                ['key' => 'genius', 'label' => 'Genius WhatsApp', 'live' => true],
                ['key' => 'meta', 'label' => 'Meta Cloud API', 'live' => false],
                ['key' => 'twilio', 'label' => 'Twilio WhatsApp', 'live' => false],
            ],
        ];
    }

    private function isConfigured(PlatformWhatsAppSettings $settings): bool
    {
        return match ($settings->provider) {
            PlatformWhatsAppSettings::PROVIDER_GENIUS => filled($this->geniusCredentials($settings)['api_key'])
                && filled($this->geniusCredentials($settings)['session_id']),
            PlatformWhatsAppSettings::PROVIDER_META => filled($settings->meta_phone_number_id)
                && filled($settings->meta_access_token),
            PlatformWhatsAppSettings::PROVIDER_TWILIO => filled($settings->twilio_account_sid)
                && filled($settings->twilio_auth_token)
                && filled($settings->twilio_from),
            default => false,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
