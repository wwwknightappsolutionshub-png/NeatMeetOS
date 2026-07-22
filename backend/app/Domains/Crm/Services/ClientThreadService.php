<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientThreadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForClient(Client $client, int $limit = 100): Collection
    {
        $this->assertTenantClient($client);

        return ClientThreadMessage::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{body: string, subject?: string|null, channel?: string, whatsapp_deeplink?: string|null, metadata?: array<string, mixed>|null}  $data
     */
    public function postOutbound(Client $client, array $data, ?string $authorUserId = null): ClientThreadMessage
    {
        $this->assertTenantClient($client);

        return DB::transaction(function () use ($client, $data, $authorUserId) {
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
}
