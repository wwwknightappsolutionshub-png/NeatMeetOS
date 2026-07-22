<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonInterface;

class TenantPresenceService
{
    public const ONLINE_WITHIN_SECONDS = 120;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function heartbeat(?Tenant $tenant = null): Tenant
    {
        $tenant = $tenant ?? $this->tenantContext->get();
        if ($tenant === null) {
            abort(422, 'Tenant context required');
        }

        $tenant->forceFill(['admin_last_seen_at' => now()])->save();

        return $tenant->fresh();
    }

    public function isOnline(?CarbonInterface $lastSeen): bool
    {
        if ($lastSeen === null) {
            return false;
        }

        return $lastSeen->greaterThan(now()->subSeconds(self::ONLINE_WITHIN_SECONDS));
    }

    /**
     * @return array{presence: string, admin_last_seen_at: string|null, online: bool}
     */
    public function presencePayload(Tenant $tenant): array
    {
        $lastSeen = $tenant->admin_last_seen_at;
        $online = $this->isOnline($lastSeen);

        return [
            'presence' => $online ? 'online' : 'offline',
            'online' => $online,
            'admin_last_seen_at' => $lastSeen?->toIso8601String(),
        ];
    }

    public function poke(Tenant $tenant, ?string $message = null): array
    {
        $title = 'NeatMeet is looking for you';
        $body = trim((string) ($message ?: 'Please open your NeatMeet workspace when you can — the platform team sent you a nudge.'));

        $result = app(PlatformTenantBroadcastService::class)->broadcast([
            'title' => $title,
            'body' => $body,
            'href' => '/admin/dashboard',
            'tenant_id' => $tenant->id,
            'send_email' => true,
            'send_push' => true,
        ]);

        $this->auditLogger->log('platform.tenant.poked', $tenant, null, [
            'title' => $title,
            'result' => $result,
        ]);

        return $result;
    }
}
