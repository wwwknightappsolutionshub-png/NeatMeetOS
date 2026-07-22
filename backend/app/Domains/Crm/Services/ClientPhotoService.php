<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientPhoto;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class ClientPhotoService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function listForClient(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $this->assertTenantClient($client);

        return ClientPhoto::query()
            ->with('uploadedBy')
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
    }

    public function register(Client $client, array $data, ?string $teamMemberId = null): ClientPhoto
    {
        $this->assertTenantClient($client);

        $photo = ClientPhoto::query()->create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'storage_path' => $data['storage_path'],
            'category' => $data['category'] ?? ClientPhoto::CATEGORY_REFERENCE,
            'caption' => $data['caption'] ?? null,
            'uploaded_by_team_member_id' => $teamMemberId,
            'is_active' => true,
        ]);

        $this->auditLogger->log('client.photo_added', $photo, null, [
            'storage_path' => $photo->storage_path,
            'category' => $photo->category,
        ]);

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_PHOTO_ADDED,
            'Photo added',
            $photo->caption,
            ['photo_id' => $photo->id, 'category' => $photo->category],
        );

        return $photo->load('uploadedBy');
    }

    public function archive(ClientPhoto $photo): ClientPhoto
    {
        $this->assertTenantPhoto($photo);

        $photo->is_active = false;
        $photo->save();

        $this->auditLogger->log('client.photo_archived', $photo);

        $this->timelineService->record(
            $photo->client,
            ClientTimelineEvent::EVENT_PHOTO_ARCHIVED,
            'Photo archived',
            $photo->caption,
            ['photo_id' => $photo->id],
        );

        return $photo->fresh();
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }

    private function assertTenantPhoto(ClientPhoto $photo): void
    {
        if ($photo->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['photo' => ['Photo not found.']]);
        }
    }
}
