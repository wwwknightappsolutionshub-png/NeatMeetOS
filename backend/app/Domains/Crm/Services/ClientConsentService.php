<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientConsentRecord;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClientConsentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function listForClient(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertTenantClient($client);

        return ClientConsentRecord::query()
            ->with('actor')
            ->where('client_id', $client->id)
            ->orderByDesc('recorded_at')
            ->get();
    }

    public function record(Client $client, array $data, ?int $actorUserId = null): ClientConsentRecord
    {
        $this->assertTenantClient($client);

        if (! in_array($data['consent_type'], ClientConsentRecord::types(), true)) {
            throw ValidationException::withMessages([
                'consent_type' => ['Invalid consent type.'],
            ]);
        }

        $source = $data['source'] ?? ClientConsentRecord::SOURCE_STAFF_ENTRY;

        if (! in_array($source, ClientConsentRecord::sources(), true)) {
            throw ValidationException::withMessages([
                'source' => ['Invalid consent source.'],
            ]);
        }

        $record = ClientConsentRecord::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'consent_type' => $data['consent_type'],
            'granted' => (bool) $data['granted'],
            'source' => $source,
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'metadata' => $data['metadata'] ?? null,
            'recorded_at' => now(),
        ]);

        $status = $record->granted ? 'granted' : 'withdrawn';

        $this->auditLogger->log('client.consent_updated', $record, null, [
            'client_id' => $client->id,
            'consent_type' => $record->consent_type,
            'granted' => $record->granted,
        ]);

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_CONSENT_UPDATED,
            'Consent '.$status.': '.$record->consent_type,
            'Source: '.$record->source,
            [
                'consent_record_id' => $record->id,
                'consent_type' => $record->consent_type,
                'granted' => $record->granted,
            ],
        );

        $this->fireMarketingConsentTrigger($client, $record->granted);

        return $record->load('actor');
    }

    /**
     * Enrol the client into any active marketing workflows keyed to consent changes.
     * Failures here must never block the consent record itself.
     */
    private function fireMarketingConsentTrigger(Client $client, bool $granted): void
    {
        try {
            $triggers = app(MarketingAutomationTriggerService::class);
            if ($granted) {
                $triggers->fireConsentGranted($client);
            } else {
                $triggers->fireConsentWithdrawn($client);
            }
        } catch (\Throwable $e) {
            Log::warning('Marketing consent trigger failed', [
                'client_id' => $client->id,
                'granted' => $granted,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function currentState(Client $client): array
    {
        $this->assertTenantClient($client);

        $latest = ClientConsentRecord::query()
            ->where('client_id', $client->id)
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('consent_type');

        return $latest->mapWithKeys(fn ($r) => [
            $r->consent_type => [
                'granted' => $r->granted,
                'recorded_at' => $r->recorded_at?->toIso8601String(),
                'source' => $r->source,
            ],
        ])->all();
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }
}
