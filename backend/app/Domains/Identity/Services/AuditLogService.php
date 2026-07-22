<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\AuditLog;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AuditLogService
{
    /**
     * Platform-wide audit listing (cross-tenant). Optional tenant_id filter.
     *
     * @param  array{
     *     tenant_id?: string|null,
     *     entity_type?: string|null,
     *     action?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     actor_id?: string|null
     * }  $filters
     */
    public function listForPlatform(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::withoutGlobalScopes()
            ->orderByDesc('created_at');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', 'like', '%'.$filters['action'].'%');
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        return $query->paginate($perPage);
    }

    public function findForPlatform(string $id): AuditLog
    {
        $log = AuditLog::withoutGlobalScopes()->where('id', $id)->first();

        if ($log === null) {
            throw ValidationException::withMessages([
                'audit_log' => ['Audit log entry not found.'],
            ]);
        }

        return $log;
    }

    public function resolveActorName(?string $actorId): ?string
    {
        if ($actorId === null) {
            return null;
        }

        return User::query()->where('id', $actorId)->value('name');
    }

    /**
     * @return array{id: string, name: string, slug: string}|null
     */
    public function resolveTenantSummary(?string $tenantId): ?array
    {
        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->trading_name ?: $tenant->name,
            'slug' => $tenant->slug,
        ];
    }
}
