<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ClientThreadService
{
    public const INBOUND_MAX_PER_MINUTE = 8;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientNoticeService $notices,
    ) {}

    public function listForClient(Client $client, int $limit = 100): Collection
    {
        $this->assertTenantClient($client);

        return ClientThreadMessage::query()
            ->where('client_id', $client->id)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Chronological for chat UI; unread outbound count for member badge.
     *
     * @return array{items: list<array<string, mixed>>, unread_count: int}
     */
    public function listForMember(Client $client, int $limit = 100): array
    {
        $messages = $this->listForClient($client, $limit);
        $unread = ClientThreadMessage::query()
            ->where('client_id', $client->id)
            ->where('direction', ClientThreadMessage::DIRECTION_OUTBOUND)
            ->whereNull('read_at')
            ->count();

        return [
            'items' => $messages->map(fn (ClientThreadMessage $m) => $this->serialize($m))->values()->all(),
            'unread_count' => $unread,
        ];
    }

    /**
     * @param  array{body: string, subject?: string|null, channel?: string, whatsapp_deeplink?: string|null, metadata?: array<string, mixed>|null}  $data
     */
    public function postOutbound(Client $client, array $data, ?string $authorUserId = null, bool $notifyMember = true): ClientThreadMessage
    {
        $this->assertTenantClient($client);

        $message = DB::transaction(function () use ($client, $data, $authorUserId) {
            $message = ClientThreadMessage::query()->create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'author_user_id' => $authorUserId,
                'direction' => ClientThreadMessage::DIRECTION_OUTBOUND,
                'channel' => $data['channel'] ?? ClientThreadMessage::CHANNEL_IN_APP,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'],
                'whatsapp_deeplink' => $data['whatsapp_deeplink'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->auditLogger->log('client_thread.message.posted', $message, null, [
                'client_id' => $client->id,
                'channel' => $message->channel,
                'direction' => $message->direction,
            ]);

            return $message->fresh();
        });

        if ($notifyMember && ($data['channel'] ?? ClientThreadMessage::CHANNEL_IN_APP) === ClientThreadMessage::CHANNEL_IN_APP) {
            $tenant = Tenant::query()->find($client->tenant_id);
            $salonName = $tenant?->trading_name ?: ($tenant?->name ?? 'Salon');
            $preview = mb_substr(trim((string) $data['body']), 0, 120);
            $this->notices->createForClient($client, [
                'type' => \App\Domains\Crm\Models\ClientNotice::TYPE_OPERATIONAL_IN_APP,
                'title' => 'New message from '.$salonName,
                'body' => $preview !== '' ? $preview : 'You have a new message in the membership app.',
                'href' => '/member/'.($tenant?->slug ?? ''),
                'data' => [
                    'source' => 'client_thread',
                    'thread_message_id' => $message->id,
                ],
            ]);
        }

        return $message;
    }

    public function postInbound(Client $client, string $body): ClientThreadMessage
    {
        $this->assertTenantClient($client);
        $trimmed = trim($body);
        if ($trimmed === '') {
            throw ValidationException::withMessages(['body' => ['Message cannot be empty.']]);
        }

        $key = 'member-thread-inbound:'.$client->id;
        if (RateLimiter::tooManyAttempts($key, self::INBOUND_MAX_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'body' => ['Please wait a moment before sending another message.'],
            ]);
        }
        RateLimiter::hit($key, 60);

        return DB::transaction(function () use ($client, $trimmed) {
            $message = ClientThreadMessage::query()->create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'author_user_id' => null,
                'direction' => ClientThreadMessage::DIRECTION_INBOUND,
                'channel' => ClientThreadMessage::CHANNEL_IN_APP,
                'subject' => null,
                'body' => $trimmed,
                'whatsapp_deeplink' => null,
                'metadata' => ['source' => 'member_pwa'],
            ]);

            $this->auditLogger->log('client_thread.message.posted', $message, null, [
                'client_id' => $client->id,
                'channel' => $message->channel,
                'direction' => $message->direction,
            ]);

            return $message->fresh();
        });
    }

    public function markOutboundReadForClient(Client $client): int
    {
        $this->assertTenantClient($client);

        return ClientThreadMessage::query()
            ->where('client_id', $client->id)
            ->where('direction', ClientThreadMessage::DIRECTION_OUTBOUND)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markInboundReadByStaff(Client $client): int
    {
        $this->assertTenantClient($client);

        return ClientThreadMessage::query()
            ->where('client_id', $client->id)
            ->where('direction', ClientThreadMessage::DIRECTION_INBOUND)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConversationSummaries(string $filter = 'open'): array
    {
        $tenantId = $this->requireTenantId();

        $clientIds = ClientThreadMessage::query()
            ->where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('client_id');

        $summaries = [];
        foreach ($clientIds as $clientId) {
            $latest = ClientThreadMessage::query()
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->orderByDesc('created_at')
                ->first();
            if ($latest === null) {
                continue;
            }

            $unreadInbound = ClientThreadMessage::query()
                ->where('tenant_id', $tenantId)
                ->where('client_id', $clientId)
                ->where('direction', ClientThreadMessage::DIRECTION_INBOUND)
                ->whereNull('read_at')
                ->count();

            $needsReply = $latest->direction === ClientThreadMessage::DIRECTION_INBOUND
                && $latest->read_at === null;

            if ($filter === 'open' && ! $needsReply && $unreadInbound === 0) {
                continue;
            }

            $client = Client::query()->find($clientId);
            if ($client === null || $client->tenant_id !== $tenantId) {
                continue;
            }

            $summaries[] = [
                'client_id' => $client->id,
                'client_name' => trim(($client->display_name ?: '') ?: (($client->first_name ?? '').' '.($client->last_name ?? ''))) ?: 'Client',
                'client_phone' => $client->phone,
                'client_email' => $client->email,
                'last_message' => $this->serialize($latest),
                'unread_inbound_count' => $unreadInbound,
                'needs_reply' => $needsReply,
            ];
        }

        usort($summaries, function (array $a, array $b) {
            if ($a['needs_reply'] !== $b['needs_reply']) {
                return $a['needs_reply'] ? -1 : 1;
            }
            $aAt = $a['last_message']['created_at'] ?? '';
            $bAt = $b['last_message']['created_at'] ?? '';

            return strcmp((string) $bAt, (string) $aAt);
        });

        return $summaries;
    }

    /**
     * WhatsApp Mode A: wa.me deeplink using tenant owner_whatsapp only.
     */
    public function buildWaMeLink(?string $ownerWhatsapp, string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $ownerWhatsapp);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    public function buildWaMeLinkForTenant(Tenant $tenant, string $text): ?string
    {
        return $this->buildWaMeLink($tenant->owner_whatsapp, $text);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ClientThreadMessage $message): array
    {
        return [
            'id' => $message->id,
            'client_id' => $message->client_id,
            'author_user_id' => $message->author_user_id,
            'direction' => $message->direction,
            'channel' => $message->channel,
            'subject' => $message->subject,
            'body' => $message->body,
            'whatsapp_deeplink' => $message->whatsapp_deeplink,
            'metadata' => $message->metadata,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $id;
    }
}
