<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientTimelineService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function record(
        Client $client,
        string $eventType,
        string $title,
        ?string $description = null,
        ?array $payload = null,
        ?int $actorUserId = null,
    ): ClientTimelineEvent {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null || $client->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'client' => ['Client not found.'],
            ]);
        }

        return ClientTimelineEvent::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    public function listForClient(Client $client, int $perPage = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ClientTimelineEvent::query()
            ->with('actor')
            ->where('client_id', $client->id)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }
}
