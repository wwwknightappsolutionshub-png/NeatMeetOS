<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientPortalToken;
use App\Domains\Crm\Models\MemberPushSubscription;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * GDPR-oriented client data export and erasure (tenant-scoped).
 */
class ClientDataPrivacyService
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function export(string $clientId): array
    {
        $client = $this->clients->find($clientId);
        $client->load([
            'notes',
            'photos',
            'documents',
            'visits',
            'tags',
            'consentRecords',
        ]);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'client' => $client->toArray(),
            'notes' => $client->notes->toArray(),
            'photos' => $client->photos->toArray(),
            'documents' => $client->documents->toArray(),
            'visits' => $client->visits->toArray(),
            'tags' => $client->tags->toArray(),
            'consent_records' => $client->relationLoaded('consentRecords')
                ? $client->consentRecords->toArray()
                : [],
            'push_subscriptions' => MemberPushSubscription::query()
                ->where('client_id', $client->id)
                ->get(['id', 'endpoint', 'created_at', 'last_seen_at'])
                ->toArray(),
        ];

        $this->auditLogger->log('client.data_exported', $client, null, [
            'sections' => array_keys($payload),
        ]);

        return $payload;
    }

    /**
     * Soft-erase personal data while retaining non-identifying operational references.
     */
    public function erase(string $clientId, ?string $reason = null): Client
    {
        $client = $this->clients->find($clientId);

        return DB::transaction(function () use ($client, $reason) {
            $old = $client->only(['email', 'phone', 'first_name', 'last_name', 'display_name', 'is_active']);

            ClientPortalToken::query()->where('client_id', $client->id)->delete();
            MemberPushSubscription::query()->where('client_id', $client->id)->delete();

            if (method_exists($client, 'notes')) {
                $client->notes()->delete();
            }
            if (method_exists($client, 'photos')) {
                $client->photos()->delete();
            }
            if (method_exists($client, 'documents')) {
                $client->documents()->delete();
            }

            $client->email = null;
            $client->phone = null;
            $client->first_name = 'Deleted';
            $client->last_name = 'Client';
            $client->display_name = 'Deleted Client';
            $client->date_of_birth = null;
            $client->special_event_month = null;
            $client->special_event_day = null;
            $client->special_event_label = null;
            $client->preferences = null;
            $client->internal_flags = array_merge($client->internal_flags ?? [], [
                'erased_at' => now()->toIso8601String(),
                'erasure_reason' => $reason,
            ]);
            $client->is_active = false;
            $client->save();

            $this->auditLogger->log('client.data_erased', $client, $old, [
                'reason' => $reason,
            ]);

            return $client->fresh();
        });
    }
}
