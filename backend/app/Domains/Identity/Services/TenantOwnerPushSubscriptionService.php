<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class TenantOwnerPushSubscriptionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{endpoint: string, keys?: array{p256dh?: string, auth?: string}, p256dh?: string, auth?: string}  $data
     * @return array{id: string, endpoint: string}
     */
    public function save(User $user, array $data): array
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            abort(422, 'Tenant context required');
        }

        $endpoint = (string) ($data['endpoint'] ?? '');
        $p256dh = (string) ($data['keys']['p256dh'] ?? $data['p256dh'] ?? '');
        $auth = (string) ($data['keys']['auth'] ?? $data['auth'] ?? '');
        $hash = hash('sha256', $endpoint);

        return DB::transaction(function () use ($tenantId, $user, $endpoint, $hash, $p256dh, $auth) {
            $row = TenantOwnerPushSubscription::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'endpoint_hash' => $hash,
                ],
                [
                    'user_id' => $user->id,
                    'endpoint' => $endpoint,
                    'p256dh' => $p256dh !== '' ? $p256dh : null,
                    'auth' => $auth !== '' ? $auth : null,
                    'last_seen_at' => now(),
                ],
            );

            $this->auditLogger->log('owner_push.subscribed', $row, null, [
                'user_id' => $user->id,
            ]);

            return [
                'id' => $row->id,
                'endpoint' => $row->endpoint,
            ];
        });
    }

    public function remove(User $user, string $endpoint): void
    {
        $tenantId = $this->tenantContext->id();
        $hash = hash('sha256', $endpoint);

        $row = TenantOwnerPushSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('endpoint_hash', $hash)
            ->first();

        if ($row !== null) {
            $row->delete();
            $this->auditLogger->log('owner_push.unsubscribed', null, [
                'user_id' => $user->id,
                'endpoint_hash' => $hash,
            ], null);
        }
    }
}
