<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientLook;
use App\Shared\Audit\AuditLogger;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClientLookService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, ClientLook>
     */
    public function listForClient(Client $client): Collection
    {
        $this->assertClientTenant($client);

        return ClientLook::query()
            ->where('client_id', $client->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->limit(ClientLook::MAX_PER_CLIENT)
            ->get();
    }

    public function upload(Client $client, UploadedFile $image, ?string $caption = null): ClientLook
    {
        $this->assertClientTenant($client);
        $tenantId = $this->requireTenantId();

        $count = ClientLook::query()->where('client_id', $client->id)->count();
        if ($count >= ClientLook::MAX_PER_CLIENT) {
            throw ValidationException::withMessages([
                'image' => ['You can save up to '.ClientLook::MAX_PER_CLIENT.' looks. Delete one to add another.'],
            ]);
        }

        $path = $image->store('client-looks/'.$tenantId.'/'.$client->id, 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        $look = ClientLook::query()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'image_url' => $url,
            'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
            'sort_order' => $count,
        ]);

        $this->auditLogger->log('crm.client_look.created', $look, null, [
            'client_id' => $client->id,
            'image_url' => $look->image_url,
        ]);

        return $look->fresh();
    }

    public function delete(Client $client, string $id): void
    {
        $this->assertClientTenant($client);

        $look = ClientLook::query()
            ->where('client_id', $client->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->assertTenant($look);

        $snapshot = $look->only(['image_url', 'caption', 'client_id']);
        $this->bestEffortDeleteFile($look->image_url);
        $look->delete();

        $this->auditLogger->log('crm.client_look.deleted', null, $snapshot, [
            'client_id' => $client->id,
            'look_id' => $id,
        ]);
    }

    public function findForClient(Client $client, string $id): ClientLook
    {
        $this->assertClientTenant($client);

        $look = ClientLook::query()
            ->where('client_id', $client->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->assertTenant($look);

        return $look;
    }

    private function bestEffortDeleteFile(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, '/storage/')) {
            return;
        }
        $relative = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');
        if ($relative === '') {
            return;
        }
        try {
            Storage::disk('public')->delete($relative);
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function assertClientTenant(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['client' => ['Client not found.']]);
        }
    }

    private function assertTenant(ClientLook $look): void
    {
        if ($look->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
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
