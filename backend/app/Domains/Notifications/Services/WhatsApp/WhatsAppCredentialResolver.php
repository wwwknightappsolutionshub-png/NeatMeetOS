<?php

namespace App\Domains\Notifications\Services\WhatsApp;

use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use App\Domains\Notifications\Models\TenantWhatsAppSettings;

/**
 * Resolve Genius credentials: tenant hosted session + platform API key, else full platform config.
 */
class WhatsAppCredentialResolver
{
    /**
     * @return array{
     *     ready: bool,
     *     provider: string,
     *     source: 'tenant'|'platform'|'none',
     *     genius: array{api_key: ?string, session_id: ?string, base_url: string}
     * }
     */
    public function resolve(?string $tenantId): array
    {
        $platformGenius = $this->platformGeniusCredentials();

        if ($tenantId) {
            $tenant = TenantWhatsAppSettings::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->first();

            if ($tenant) {
                $this->expireIfNeeded($tenant);
                $tenant = $tenant->fresh() ?? $tenant;
            }

            if ($tenant?->isHostedActive() && filled($platformGenius['api_key'])) {
                return [
                    'ready' => true,
                    'provider' => 'genius',
                    'source' => 'tenant',
                    'genius' => [
                        'api_key' => $platformGenius['api_key'],
                        'session_id' => $tenant->hosted_session_id,
                        'base_url' => $platformGenius['base_url'],
                    ],
                ];
            }
        }

        $platform = PlatformWhatsAppSettings::query()->orderBy('created_at')->first();
        if ($platform
            && $platform->enabled
            && $platform->provider === PlatformWhatsAppSettings::PROVIDER_GENIUS
            && filled($platformGenius['api_key'])
            && filled($platformGenius['session_id'])
        ) {
            return [
                'ready' => true,
                'provider' => 'genius',
                'source' => 'platform',
                'genius' => $platformGenius,
            ];
        }

        return [
            'ready' => false,
            'provider' => $platform?->provider ?: 'genius',
            'source' => 'none',
            'genius' => $platformGenius,
        ];
    }

    public function hasSendableCredentials(?string $tenantId): bool
    {
        return $this->resolve($tenantId)['ready'];
    }

    /**
     * @return array{api_key: ?string, session_id: ?string, base_url: string}
     */
    public function platformGeniusCredentials(): array
    {
        $settings = PlatformWhatsAppSettings::query()->orderBy('created_at')->first();

        return [
            'api_key' => $settings?->api_key ?: config('whatsapp.genius.api_key'),
            'session_id' => $settings?->session_id ?: config('whatsapp.genius.session_id'),
            'base_url' => rtrim((string) ($settings?->base_url ?: config('whatsapp.genius.base_url')), '/'),
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
}
