<?php

namespace App\Shared\Audit;

use App\Domains\Identity\Models\AuditLog;
use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $actor = $actor ?? auth()->user();

        return AuditLog::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantContext->id(),
            'actor_type' => $actor ? User::class : 'system',
            'actor_id' => $actor ? (string) $actor->id : null,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
