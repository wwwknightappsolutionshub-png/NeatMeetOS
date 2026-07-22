<?php

namespace App\Domains\Identity\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\TenantOwnerPushSubscription;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;

/**
 * Cross-tenant view of installed PWA push subscribers (admin owners + members).
 */
class PlatformPwaUserService
{
    public function __construct(
        private readonly MemberPushDispatchService $push,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsers(?string $type = null): array
    {
        $rows = [];

        if ($type === null || $type === 'admin') {
            $owners = TenantOwnerPushSubscription::withoutGlobalScopes()
                ->orderByDesc('last_seen_at')
                ->orderByDesc('updated_at')
                ->limit(500)
                ->get();

            $tenantIds = $owners->pluck('tenant_id')->unique()->all();
            $userIds = $owners->pluck('user_id')->unique()->all();
            $tenants = Tenant::query()->whereIn('id', $tenantIds)->get()->keyBy('id');
            $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

            foreach ($owners as $sub) {
                $tenant = $tenants->get($sub->tenant_id);
                $user = $users->get($sub->user_id);
                $rows[] = [
                    'id' => $sub->id,
                    'type' => 'admin',
                    'tenant_id' => $sub->tenant_id,
                    'tenant_name' => $tenant?->name,
                    'tenant_slug' => $tenant?->slug,
                    'user_id' => $sub->user_id,
                    'display_name' => $user?->name,
                    'email' => $user?->email,
                    'last_seen_at' => $sub->last_seen_at?->toIso8601String(),
                    'created_at' => $sub->created_at?->toIso8601String(),
                ];
            }
        }

        if ($type === null || $type === 'member') {
            $members = MemberPushSubscription::withoutGlobalScopes()
                ->orderByDesc('last_seen_at')
                ->orderByDesc('updated_at')
                ->limit(500)
                ->get();

            $tenantIds = $members->pluck('tenant_id')->unique()->all();
            $clientIds = $members->pluck('client_id')->unique()->all();
            $tenants = Tenant::query()->whereIn('id', $tenantIds)->get()->keyBy('id');
            $clients = Client::withoutGlobalScopes()->whereIn('id', $clientIds)->get()->keyBy('id');

            foreach ($members as $sub) {
                $tenant = $tenants->get($sub->tenant_id);
                $client = $clients->get($sub->client_id);
                $rows[] = [
                    'id' => $sub->id,
                    'type' => 'member',
                    'tenant_id' => $sub->tenant_id,
                    'tenant_name' => $tenant?->name,
                    'tenant_slug' => $tenant?->slug,
                    'user_id' => $sub->client_id,
                    'display_name' => trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: null,
                    'email' => $client?->email,
                    'last_seen_at' => $sub->last_seen_at?->toIso8601String(),
                    'created_at' => $sub->created_at?->toIso8601String(),
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            return strcmp((string) ($b['last_seen_at'] ?? ''), (string) ($a['last_seen_at'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param  array{title: string, body: string, url?: string|null, subscription_ids?: list<string>|null, type?: string|null}  $payload
     * @return array{sent: int, failed: int, skipped: int, targeted: int}
     */
    public function push(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $url = $payload['url'] ?? null;
        $ids = $payload['subscription_ids'] ?? null;
        $type = $payload['type'] ?? null;

        $ownerSubs = collect();
        $memberSubs = collect();

        if ($type === null || $type === 'admin') {
            $q = TenantOwnerPushSubscription::withoutGlobalScopes();
            if (is_array($ids) && $ids !== []) {
                $q->whereIn('id', $ids);
            }
            $ownerSubs = $q->get();
        }

        if ($type === null || $type === 'member') {
            $q = MemberPushSubscription::withoutGlobalScopes();
            if (is_array($ids) && $ids !== []) {
                $q->whereIn('id', $ids);
            }
            $memberSubs = $q->get();
        }

        // When filtering by explicit IDs, only keep matching type rows that were requested.
        if (is_array($ids) && $ids !== []) {
            if ($type === 'admin') {
                $memberSubs = collect();
            } elseif ($type === 'member') {
                $ownerSubs = collect();
            }
        }

        $pushPayload = [
            'title' => $title,
            'body' => $body,
            'url' => is_string($url) ? $url : null,
            'data' => ['source' => 'platform_pwa_push'],
        ];

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        if ($ownerSubs->isNotEmpty()) {
            $r = $this->push->sendToSubscriptions($ownerSubs->all(), $pushPayload, 'owner_push.platform_dispatched');
            $sent += $r['sent'];
            $failed += $r['failed'];
            $skipped += $r['skipped'];
        }

        if ($memberSubs->isNotEmpty()) {
            $r = $this->push->sendToSubscriptions($memberSubs->all(), $pushPayload, 'member_push.platform_dispatched');
            $sent += $r['sent'];
            $failed += $r['failed'];
            $skipped += $r['skipped'];
        }

        $targeted = $ownerSubs->count() + $memberSubs->count();

        $this->auditLogger->log('platform.pwa_push', null, null, [
            'title' => $title,
            'type' => $type,
            'targeted' => $targeted,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'targeted' => $targeted,
        ];
    }
}
