<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTag;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientTagService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
    ) {}

    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return ClientTag::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): ClientTag
    {
        $tenantId = $this->requireTenantId();
        $slug = $data['slug'] ?? Str::slug($data['name']);

        if (ClientTag::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['A tag with this slug already exists.'],
            ]);
        }

        return ClientTag::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?? null,
            'is_active' => true,
        ]);
    }

    public function update(ClientTag $tag, array $data): ClientTag
    {
        $this->assertTenantTag($tag);
        $tag->fill(array_intersect_key($data, array_flip(['name', 'color'])));
        $tag->save();

        return $tag->fresh();
    }

    public function syncClientTags(Client $client, array $tagIds): Client
    {
        $tenantId = $this->requireTenantId();

        if ($client->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }

        $validIds = ClientTag::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count(array_unique($tagIds))) {
            throw ValidationException::withMessages([
                'tag_ids' => ['One or more tags are invalid.'],
            ]);
        }

        $oldIds = $client->tags()->pluck('client_tags.id')->all();
        $client->tags()->sync($validIds);

        $added = array_diff($validIds, $oldIds);
        $removed = array_diff($oldIds, $validIds);

        foreach ($added as $tagId) {
            $tag = ClientTag::query()->find($tagId);
            $this->timelineService->record(
                $client,
                ClientTimelineEvent::EVENT_TAG_ASSIGNED,
                'Tag assigned: '.($tag?->name ?? $tagId),
                null,
                ['tag_id' => $tagId],
            );
        }

        foreach ($removed as $tagId) {
            $tag = ClientTag::query()->find($tagId);
            $this->timelineService->record(
                $client,
                ClientTimelineEvent::EVENT_TAG_REMOVED,
                'Tag removed: '.($tag?->name ?? $tagId),
                null,
                ['tag_id' => $tagId],
            );
        }

        if ($added !== [] || $removed !== []) {
            $this->auditLogger->log(
                'client.tags_updated',
                $client,
                ['tag_ids' => $oldIds],
                ['tag_ids' => $validIds],
            );
        }

        return $client->fresh(['tags', 'primaryLocation']);
    }

    private function assertTenantTag(ClientTag $tag): void
    {
        if ($tag->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['tag' => ['Tag not found.']]);
        }
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $tenantId;
    }
}
