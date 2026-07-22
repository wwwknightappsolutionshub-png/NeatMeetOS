<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\Workspace;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamMemberService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return TeamMember::query()
            ->with(['user', 'roles', 'primaryLocation', 'workspaces'])
            ->orderBy('display_name')
            ->get();
    }

    public function find(string $id): TeamMember
    {
        return TeamMember::query()
            ->with(['user', 'roles', 'primaryLocation', 'workspaces'])
            ->findOrFail($id);
    }

    public function create(array $data): TeamMember
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return DB::transaction(function () use ($data, $tenantId) {
            $this->validateLocationAndWorkspaces($data, $tenantId);

            $user = User::query()->where('email', $data['email'])->first();

            if ($user === null) {
                $displayName = $data['display_name']
                    ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

                $user = User::factory()->create([
                    'name' => $displayName ?: $data['email'],
                    'email' => $data['email'],
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            $existing = TeamMember::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a team member of this organization.'],
                ]);
            }

            $displayName = $data['display_name']
                ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

            $teamMember = TeamMember::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'display_name' => $displayName ?: $user->name,
                'phone' => $data['phone'] ?? null,
                'employment_type' => $data['employment_type'],
                'primary_location_id' => $data['primary_location_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (! empty($data['workspace_ids'])) {
                $teamMember->workspaces()->sync($data['workspace_ids']);
            }

            if (! empty($data['role_ids'])) {
                $this->syncRoles($teamMember, $data['role_ids'], $tenantId);
            }

            $this->auditLogger->log('team_member.created', $teamMember, null, $teamMember->toArray());

            return $teamMember->fresh()->load(['user', 'roles', 'primaryLocation', 'workspaces']);
        });
    }

    public function update(TeamMember $teamMember, array $data): TeamMember
    {
        return DB::transaction(function () use ($teamMember, $data) {
            $this->validateLocationAndWorkspaces($data, $teamMember->tenant_id, false);

            $old = $teamMember->toArray();

            $teamMember->fill(collect($data)->only([
                'first_name',
                'last_name',
                'display_name',
                'phone',
                'employment_type',
                'primary_location_id',
            ])->filter(fn ($v) => $v !== null)->all());

            $teamMember->save();

            if (array_key_exists('workspace_ids', $data)) {
                $teamMember->workspaces()->sync($data['workspace_ids'] ?? []);
            }

            $this->auditLogger->log('team_member.updated', $teamMember, $old, $teamMember->toArray());

            return $teamMember->fresh()->load(['user', 'roles', 'primaryLocation', 'workspaces']);
        });
    }

    public function setActive(TeamMember $teamMember, bool $isActive): TeamMember
    {
        $old = ['is_active' => $teamMember->is_active];
        $teamMember->is_active = $isActive;
        $teamMember->save();

        $action = $isActive ? 'team_member.activated' : 'team_member.deactivated';
        $this->auditLogger->log($action, $teamMember, $old, ['is_active' => $isActive]);

        return $teamMember->fresh()->load(['user', 'roles', 'primaryLocation', 'workspaces']);
    }

    public function syncRoles(TeamMember $teamMember, array $roleIds, ?string $tenantId = null): TeamMember
    {
        $tenantId = $tenantId ?? $teamMember->tenant_id;

        $validRoleIds = Role::query()
            ->whereIn('id', $roleIds)
            ->where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->pluck('id')
            ->all();

        if (count($validRoleIds) !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => ['One or more roles are invalid for this tenant.'],
            ]);
        }

        $old = $teamMember->roles()->pluck('roles.id')->all();
        $teamMember->roles()->sync($validRoleIds);

        $this->auditLogger->log(
            'team_member.roles_updated',
            $teamMember,
            ['role_ids' => $old],
            ['role_ids' => $validRoleIds],
        );

        return $teamMember->fresh()->load('roles');
    }

    private function validateLocationAndWorkspaces(array $data, string $tenantId, bool $requireLocation = true): void
    {
        if ($requireLocation && empty($data['primary_location_id']) && ($data['employment_type'] ?? '') !== TeamMember::EMPLOYMENT_OWNER) {
            // primary location optional for owner at bootstrap
        }

        if (! empty($data['primary_location_id'])) {
            $valid = Location::query()
                ->where('id', $data['primary_location_id'])
                ->where('tenant_id', $tenantId)
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'primary_location_id' => ['Location does not belong to this tenant.'],
                ]);
            }
        }

        if (! empty($data['workspace_ids'])) {
            $count = Workspace::query()
                ->whereIn('id', $data['workspace_ids'])
                ->where('tenant_id', $tenantId)
                ->count();

            if ($count !== count($data['workspace_ids'])) {
                throw ValidationException::withMessages([
                    'workspace_ids' => ['One or more workspaces are invalid for this tenant.'],
                ]);
            }
        }
    }
}
