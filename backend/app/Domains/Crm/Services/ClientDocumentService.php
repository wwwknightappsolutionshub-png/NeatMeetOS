<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientDocument;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class ClientDocumentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function listForClient(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertTenantClient($client);

        return ClientDocument::query()
            ->with('uploadedBy')
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
    }

    public function register(Client $client, array $data, ?string $teamMemberId = null): ClientDocument
    {
        $this->assertTenantClient($client);

        $document = ClientDocument::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? ClientDocument::TYPE_REFERENCE,
            'storage_path' => $data['storage_path'],
            'description' => $data['description'] ?? null,
            'uploaded_by_team_member_id' => $teamMemberId,
            'is_active' => true,
        ]);

        $this->auditLogger->log('client.document_added', $document, null, [
            'title' => $document->title,
            'document_type' => $document->document_type,
        ]);

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_DOCUMENT_ADDED,
            'Document added: '.$document->title,
            $document->description,
            ['document_id' => $document->id],
        );

        return $document->load('uploadedBy');
    }

    public function archive(ClientDocument $document): ClientDocument
    {
        $this->assertTenantDocument($document);

        $document->is_active = false;
        $document->save();

        $this->auditLogger->log('client.document_archived', $document);

        $this->timelineService->record(
            $document->client,
            ClientTimelineEvent::EVENT_DOCUMENT_ARCHIVED,
            'Document archived: '.$document->title,
            null,
            ['document_id' => $document->id],
        );

        return $document->fresh();
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }

    private function assertTenantDocument(ClientDocument $document): void
    {
        if ($document->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['document' => ['Document not found.']]);
        }
    }
}
