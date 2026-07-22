<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WorkspaceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(?string $locationId = null): Collection
    {
        $query = Workspace::query()->with('location')->orderBy('name');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    public function find(string $id): Workspace
    {
        return Workspace::query()->with('location')->findOrFail($id);
    }

    public function create(array $data): Workspace
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        $this->assertLocationBelongsToTenant($data['location_id'], $tenantId);

        $data['tenant_id'] = $tenantId;
        $data['is_active'] = $data['is_active'] ?? true;

        $workspace = Workspace::query()->create($data);

        $this->auditLogger->log('workspace.created', $workspace, null, $workspace->toArray());

        return $workspace->load('location');
    }

    public function update(Workspace $workspace, array $data): Workspace
    {
        if (isset($data['location_id'])) {
            $this->assertLocationBelongsToTenant($data['location_id'], $workspace->tenant_id);
        }

        $old = $workspace->toArray();
        $workspace->fill($data);
        $workspace->save();

        $this->auditLogger->log('workspace.updated', $workspace, $old, $workspace->toArray());

        return $workspace->fresh()->load('location');
    }

    public function setActive(Workspace $workspace, bool $isActive): Workspace
    {
        $old = ['is_active' => $workspace->is_active];
        $workspace->is_active = $isActive;
        $workspace->save();

        $action = $isActive ? 'workspace.activated' : 'workspace.deactivated';
        $this->auditLogger->log($action, $workspace, $old, ['is_active' => $isActive]);

        return $workspace->fresh()->load('location');
    }

    private function assertLocationBelongsToTenant(string $locationId, string $tenantId): void
    {
        $exists = Location::query()
            ->where('id', $locationId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'location_id' => ['Location does not belong to this tenant.'],
            ]);
        }
    }
}
