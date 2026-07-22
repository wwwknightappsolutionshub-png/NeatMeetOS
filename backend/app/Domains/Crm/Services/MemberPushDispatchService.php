<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\MemberPushSubscription;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * True Web Push (VAPID) delivery to member PWA subscriptions.
 */
class MemberPushDispatchService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function publicKey(): ?string
    {
        $key = config('webpush.vapid.public_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->publicKey() !== null
            && is_string(config('webpush.vapid.private_key'))
            && config('webpush.vapid.private_key') !== '';
    }

    /**
     * @param  array{title?: string, body?: string, url?: string, data?: array<string, mixed>}  $payload
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToClient(string $clientId, array $payload): array
    {
        $subscriptions = MemberPushSubscription::query()
            ->where('client_id', $clientId)
            ->get();

        return $this->sendToSubscriptions($subscriptions->all(), $payload);
    }

    /**
     * @param  array{title?: string, body?: string, url?: string, data?: array<string, mixed>}  $payload
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToTenant(array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->tenantContext->id();
        $subscriptions = MemberPushSubscription::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        return $this->sendToSubscriptions($subscriptions->all(), $payload);
    }

    /**
     * @param  list<object{endpoint: string, p256dh?: ?string, auth?: ?string, id?: string, tenant_id?: string}>  $subscriptions
     * @param  array{title?: string, body?: string, url?: string, data?: array<string, mixed>}  $payload
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToSubscriptions(array $subscriptions, array $payload, string $auditAction = 'member_push.dispatched'): array
    {
        if ($subscriptions === []) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        if (! $this->isConfigured()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => count($subscriptions)];
        }

        try {
            $webPush = $this->makeWebPush();
        } catch (Throwable $e) {
            Log::warning('web_push.vapid_invalid', ['message' => $e->getMessage()]);

            return ['sent' => 0, 'failed' => 0, 'skipped' => count($subscriptions)];
        }

        $body = json_encode([
            'title' => $payload['title'] ?? 'NeatMeet',
            'body' => $payload['body'] ?? '',
            'url' => $payload['url'] ?? null,
            'data' => $payload['data'] ?? [],
        ], JSON_THROW_ON_ERROR);

        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $row) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $row->endpoint,
                    'keys' => [
                        'p256dh' => $row->p256dh,
                        'auth' => $row->auth,
                    ],
                ]);

                $report = $webPush->sendOneNotification($subscription, $body);
                if ($report->isSuccess()) {
                    $sent++;
                    if (property_exists($row, 'last_seen_at') || method_exists($row, 'setAttribute')) {
                        $row->last_seen_at = now();
                        $row->save();
                    }
                } else {
                    $failed++;
                    if ($report->isSubscriptionExpired()) {
                        $row->delete();
                    }
                    Log::warning('web_push.failed', [
                        'subscription_id' => $row->id ?? null,
                        'reason' => $report->getReason(),
                    ]);
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('web_push.exception', [
                    'subscription_id' => $row->id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $tenantId = $subscriptions[0]->tenant_id ?? $this->tenantContext->id();
        if ($tenantId !== null) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant !== null) {
                $this->auditLogger->log($auditAction, $tenant, null, [
                    'sent' => $sent,
                    'failed' => $failed,
                    'skipped' => 0,
                    'title' => $payload['title'] ?? null,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => 0];
    }

    private function makeWebPush(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);
    }
}
