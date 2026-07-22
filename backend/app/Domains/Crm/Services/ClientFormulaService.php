<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientFormula;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class ClientFormulaService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function listForClient(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertTenantClient($client);

        return ClientFormula::query()
            ->with('recordedBy')
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function find(string $id): ClientFormula
    {
        return ClientFormula::query()
            ->with(['recordedBy', 'client'])
            ->findOrFail($id);
    }

    public function create(Client $client, array $data, ?string $teamMemberId = null): ClientFormula
    {
        $this->assertTenantClient($client);

        $formula = ClientFormula::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'title' => $data['title'],
            'formula_body' => $data['formula_body'],
            'category' => $data['category'] ?? ClientFormula::CATEGORY_OTHER,
            'service_context' => $data['service_context'] ?? null,
            'recorded_by_team_member_id' => $teamMemberId,
            'is_active' => true,
        ]);

        $this->auditLogger->log('client.formula_created', $formula, null, ['title' => $formula->title]);

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_FORMULA_CREATED,
            'Formula added: '.$formula->title,
            \Illuminate\Support\Str::limit($formula->formula_body, 120),
            ['formula_id' => $formula->id],
        );

        return $formula->load('recordedBy');
    }

    public function update(ClientFormula $formula, array $data): ClientFormula
    {
        $this->assertTenantFormula($formula);

        $old = $formula->only(array_keys($data));
        $formula->fill($data);
        $formula->save();

        $this->auditLogger->log('client.formula_updated', $formula, $old, $formula->only(array_keys($data)));

        $this->timelineService->record(
            $formula->load('client')->client,
            ClientTimelineEvent::EVENT_FORMULA_UPDATED,
            'Formula updated: '.$formula->title,
            null,
            ['formula_id' => $formula->id],
        );

        return $formula->fresh(['recordedBy']);
    }

    public function archive(ClientFormula $formula): ClientFormula
    {
        $this->assertTenantFormula($formula);

        $formula->is_active = false;
        $formula->save();

        $this->auditLogger->log('client.formula_archived', $formula);

        $this->timelineService->record(
            $formula->load('client')->client,
            ClientTimelineEvent::EVENT_FORMULA_ARCHIVED,
            'Formula archived: '.$formula->title,
        );

        return $formula->fresh();
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }

    private function assertTenantFormula(ClientFormula $formula): void
    {
        if ($formula->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['formula' => ['Formula not found.']]);
        }
    }
}
