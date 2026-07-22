<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    private const OWNER_PROTECTED_PERMISSIONS = [
        'identity.view',
        'identity.manage',
        'identity.access.manage',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForTenant(): Collection
    {
        $tenantId = $this->tenantContext->id();

        return Role::query()
            ->with(['permissions', 'teamMembers'])
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): Role
    {
        $role = Role::query()
            ->with(['permissions', 'teamMembers'])
            ->where('tenant_id', $this->tenantContext->id())
            ->where('id', $id)
            ->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => ['Role not found.'],
            ]);
        }

        return $role;
    }

    public function create(array $data): Role
    {
        $tenantId = $this->tenantContext->id();
        $slug = $data['slug'] ?? Str::slug($data['name']);

        if (Role::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['A role with this slug already exists.'],
            ]);
        }

        $role = Role::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
        ]);

        if (! empty($data['permission_ids'])) {
            $this->syncPermissions($role, $data['permission_ids']);
        }

        $this->auditLogger->log('role.created', $role, null, $role->only(['name', 'slug']));

        return $role->fresh(['permissions', 'teamMembers']);
    }

    public function update(Role $role, array $data): Role
    {
        $this->assertTenantRole($role);

        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            throw ValidationException::withMessages([
                'slug' => ['System role slugs cannot be changed.'],
            ]);
        }

        $old = $role->only(['name', 'slug']);
        $role->fill(array_intersect_key($data, array_flip(['name', 'slug'])));
        $role->save();

        $this->auditLogger->log('role.updated', $role, $old, $role->only(['name', 'slug']));

        return $role->fresh(['permissions', 'teamMembers']);
    }

    public function archive(Role $role): Role
    {
        $this->assertTenantRole($role);

        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => ['System roles cannot be archived.'],
            ]);
        }

        if ($role->teamMembers()->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Remove team members from this role before archiving.'],
            ]);
        }

        $role->is_active = false;
        $role->save();

        $this->auditLogger->log('role.archived', $role);

        return $role->fresh(['permissions', 'teamMembers']);
    }

    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $this->assertTenantRole($role);

        $validIds = Permission::query()->whereIn('id', $permissionIds)->pluck('id')->all();

        if (count($validIds) !== count(array_unique($permissionIds))) {
            throw ValidationException::withMessages([
                'permission_ids' => ['One or more permissions are invalid.'],
            ]);
        }

        if ($role->slug === 'owner') {
            $validIds = array_values(array_unique(array_merge($validIds, self::OWNER_PROTECTED_PERMISSIONS)));
        }

        $old = $role->permissions()->pluck('permissions.id')->all();
        $role->permissions()->sync($validIds);

        $this->auditLogger->log(
            'role.permissions_updated',
            $role,
            ['permission_ids' => $old],
            ['permission_ids' => $validIds],
        );

        return $role->fresh(['permissions', 'teamMembers']);
    }

    public function assertAssignable(Role $role): void
    {
        if (! $role->is_active) {
            throw ValidationException::withMessages([
                'role_ids' => ['Archived roles cannot be assigned.'],
            ]);
        }
    }

    private function assertTenantRole(Role $role): void
    {
        if ($role->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages([
                'role' => ['Role not found.'],
            ]);
        }
    }
}
