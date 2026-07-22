<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNote;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientNoteService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function listForClient(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertTenantClient($client);

        return ClientNote::query()
            ->with('author')
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(Client $client, array $data, ?string $authorTeamMemberId = null): ClientNote
    {
        $this->assertTenantClient($client);

        if (! in_array($data['note_type'] ?? ClientNote::TYPE_GENERAL, ClientNote::types(), true)) {
            throw ValidationException::withMessages([
                'note_type' => ['Invalid note type.'],
            ]);
        }

        $note = ClientNote::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'author_team_member_id' => $authorTeamMemberId,
            'note_type' => $data['note_type'] ?? ClientNote::TYPE_GENERAL,
            'body' => $data['body'],
        ]);

        $this->auditLogger->log('client.note_added', $note, null, [
            'client_id' => $client->id,
            'note_type' => $note->note_type,
        ]);

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_NOTE_ADDED,
            'Note added',
            Str::limit($note->body, 120),
            ['note_id' => $note->id, 'note_type' => $note->note_type],
        );

        return $note->load('author');
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }
}
