<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\TenantWhatsAppSettings;
use App\Domains\Notifications\Services\WhatsApp\WhatsAppCredentialResolver;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantWhatsAppSettingsService
{
    public const HOSTED_SESSION_TTL_DAYS = 30;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly WhatsAppCredentialResolver $credentials,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->serialize($this->getOrCreate($this->requireTenantId()));
    }

    /**
     * @return array<string, mixed>
     */
    public function initHostedSession(): array
    {
        $tenantId = $this->requireTenantId();
        $settings = $this->getOrCreate($tenantId);

        $sessionId = $settings->hosted_session_id ?: $this->generateHostedSessionId();
        $qrPayload = json_encode([
            'type' => 'neatmeet_whatsapp_hosted_session',
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'issued_at' => now()->toIso8601String(),
            'note' => 'Scan this session in your Genius WhatsApp gateway, then confirm the phone number below.',
        ], JSON_THROW_ON_ERROR);

        $settings->update([
            'enabled' => true,
            'provider' => 'genius',
            'hosted_session_id' => $sessionId,
            'hosted_status' => TenantWhatsAppSettings::STATUS_PENDING_SCAN,
            'hosted_qr_payload' => $qrPayload,
            'hosted_last_seen_at' => now(),
            'hosted_phone_number' => null,
            'hosted_connected_at' => null,
            'hosted_expires_at' => null,
        ]);

        $this->auditLogger->log('tenant.whatsapp_session.initialized', $settings, null, [
            'hosted_status' => TenantWhatsAppSettings::STATUS_PENDING_SCAN,
        ]);

        return $this->serialize($settings->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function activateHostedSession(string $phoneNumber): array
    {
        $tenantId = $this->requireTenantId();
        $settings = $this->getOrCreate($tenantId);

        if (! filled($settings->hosted_session_id)) {
            throw ValidationException::withMessages([
                'session' => ['Initialize a WhatsApp scan session first.'],
            ]);
        }

        $normalized = preg_replace('/\s+/', '', trim($phoneNumber)) ?? '';
        if ($normalized === '' || ! str_starts_with($normalized, '+')) {
            throw ValidationException::withMessages([
                'phone_number' => ['Provide an E.164 WhatsApp number, e.g. +447700900123.'],
            ]);
        }

        if (! filled($this->credentials->platformGeniusCredentials()['api_key'])) {
            throw ValidationException::withMessages([
                'session' => ['Platform Genius API key is not configured. Ask the platform admin to enable WhatsApp first.'],
            ]);
        }

        $expiresAt = now()->addDays(self::HOSTED_SESSION_TTL_DAYS);
        $settings->update([
            'enabled' => true,
            'provider' => 'genius',
            'hosted_phone_number' => $normalized,
            'hosted_status' => TenantWhatsAppSettings::STATUS_ACTIVE,
            'hosted_connected_at' => now(),
            'hosted_last_seen_at' => now(),
            'hosted_expires_at' => $expiresAt,
        ]);

        $this->auditLogger->log('tenant.whatsapp_session.activated', $settings, null, [
            'hosted_status' => TenantWhatsAppSettings::STATUS_ACTIVE,
            'hosted_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return $this->serialize($settings->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshHostedSession(): array
    {
        $settings = $this->getOrCreate($this->requireTenantId());
        $this->expireIfNeeded($settings);
        $settings = $settings->fresh() ?? $settings;

        if ($settings->hosted_status !== TenantWhatsAppSettings::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'session' => ['Only an active WhatsApp session can be refreshed.'],
            ]);
        }

        $expiresAt = now()->addDays(self::HOSTED_SESSION_TTL_DAYS);
        $settings->update([
            'hosted_expires_at' => $expiresAt,
            'hosted_last_seen_at' => now(),
        ]);

        $this->auditLogger->log('tenant.whatsapp_session.refreshed', $settings, null, [
            'hosted_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return $this->serialize($settings->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function disconnectHostedSession(): array
    {
        $settings = $this->getOrCreate($this->requireTenantId());

        $settings->update([
            'enabled' => false,
            'hosted_phone_number' => null,
            'hosted_status' => TenantWhatsAppSettings::STATUS_DISCONNECTED,
            'hosted_qr_payload' => null,
            'hosted_connected_at' => null,
            'hosted_last_seen_at' => now(),
            'hosted_expires_at' => null,
        ]);

        $this->auditLogger->log('tenant.whatsapp_session.disconnected', $settings, null, [
            'hosted_status' => TenantWhatsAppSettings::STATUS_DISCONNECTED,
        ]);

        return $this->serialize($settings->fresh());
    }

    public function getOrCreate(string $tenantId): TenantWhatsAppSettings
    {
        $settings = TenantWhatsAppSettings::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($settings) {
            $this->expireIfNeeded($settings);

            return $settings->fresh() ?? $settings;
        }

        return TenantWhatsAppSettings::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'enabled' => false,
            'provider' => 'genius',
            'hosted_status' => TenantWhatsAppSettings::STATUS_INACTIVE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TenantWhatsAppSettings $settings): array
    {
        $resolved = $this->credentials->resolve($settings->tenant_id);
        $expiresAt = $settings->hosted_expires_at;
        $remainingDays = $expiresAt
            ? max(0, (int) now()->diffInDays($expiresAt, false))
            : null;

        return [
            'tenant_id' => $settings->tenant_id,
            'enabled' => (bool) $settings->enabled,
            'provider' => $settings->provider ?: 'genius',
            'hosted_session' => [
                'session_id' => $settings->hosted_session_id,
                'phone_number' => $settings->hosted_phone_number,
                'status' => $settings->hosted_status ?: TenantWhatsAppSettings::STATUS_INACTIVE,
                'qr_payload' => $settings->hosted_qr_payload,
                'connected_at' => $settings->hosted_connected_at?->toIso8601String(),
                'last_seen_at' => $settings->hosted_last_seen_at?->toIso8601String(),
                'expires_at' => $settings->hosted_expires_at?->toIso8601String(),
                'remaining_days' => $remainingDays,
                'lifecycle_days' => self::HOSTED_SESSION_TTL_DAYS,
            ],
            'using_platform_fallback' => $resolved['source'] !== 'tenant',
            'active_source' => $resolved['source'],
            'active_provider' => $resolved['provider'],
            'ready' => $resolved['ready'],
            'platform_api_configured' => filled($resolved['genius']['api_key'] ?? null),
        ];
    }

    private function expireIfNeeded(TenantWhatsAppSettings $settings): void
    {
        if ($settings->hosted_status !== TenantWhatsAppSettings::STATUS_ACTIVE
            || $settings->hosted_expires_at === null
            || $settings->hosted_expires_at->isFuture()
        ) {
            return;
        }

        $settings->update([
            'hosted_status' => TenantWhatsAppSettings::STATUS_EXPIRED,
            'hosted_qr_payload' => null,
        ]);
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            abort(400, 'Tenant could not be resolved');
        }

        return $tenantId;
    }

    private function generateHostedSessionId(): string
    {
        return 'session_'.Str::lower(Str::random(18));
    }
}
