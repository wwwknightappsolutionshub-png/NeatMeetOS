<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Member in-app inbox (mirrors TenantOwnerNotice for salon clients).
 */
class ClientNoticeService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{title: string, body: string, href?: string|null, type?: string, marketing_message_id?: string|null, data?: array<string, mixed>|null}  $payload
     */
    public function createForClient(Client $client, array $payload): ClientNotice
    {
        $this->assertTenantClient($client);

        return DB::transaction(function () use ($client, $payload) {
            $notice = ClientNotice::query()->create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'marketing_message_id' => $payload['marketing_message_id'] ?? null,
                'type' => $payload['type'] ?? ClientNotice::TYPE_MARKETING_IN_APP,
                'title' => $payload['title'],
                'body' => $payload['body'],
                'href' => $payload['href'] ?? null,
                'data' => $payload['data'] ?? null,
            ]);

            $this->auditLogger->log('client_notice.created', $notice, null, [
                'client_id' => $client->id,
                'type' => $notice->type,
                'marketing_message_id' => $notice->marketing_message_id,
            ]);

            return $notice->fresh();
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread_count: int}
     */
    public function listForClient(Client $client, int $limit = 40): array
    {
        $this->assertTenantClient($client);

        $items = ClientNotice::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ClientNotice $n) => $this->serialize($n))
            ->all();

        $unread = ClientNotice::query()
            ->where('client_id', $client->id)
            ->whereNull('read_at')
            ->count();

        return [
            'items' => $items,
            'unread_count' => $unread,
        ];
    }

    public function markRead(Client $client, string $noticeId): ClientNotice
    {
        $this->assertTenantClient($client);

        $notice = ClientNotice::query()
            ->where('client_id', $client->id)
            ->findOrFail($noticeId);

        if ($notice->read_at === null) {
            $notice->forceFill(['read_at' => now()])->save();
            $this->auditLogger->log('client_notice.read', $notice, null, [
                'client_id' => $client->id,
            ]);
        }

        return $notice->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ClientNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'type' => $notice->type,
            'title' => $notice->title,
            'body' => $notice->body,
            'href' => $notice->href,
            'data' => $notice->data,
            'read_at' => $notice->read_at?->toIso8601String(),
            'created_at' => $notice->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }
}
