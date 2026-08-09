<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Permanently remove a salon tenant and tenant-scoped data from the database.
 */
class TenantPurgeService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{confirmation_slug?: string, confirm?: bool}  $data
     * @return array{purged: bool, tenant_id: string, slug: string, name: string}
     */
    public function purge(Tenant $tenant, array $data, ?User $actor = null): array
    {
        $confirmationSlug = strtolower(trim((string) ($data['confirmation_slug'] ?? '')));
        $confirmed = filter_var($data['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm' => ['Set confirm=true to permanently delete this tenant.'],
            ]);
        }

        if ($confirmationSlug === '' || $confirmationSlug !== strtolower((string) $tenant->slug)) {
            throw ValidationException::withMessages([
                'confirmation_slug' => ['Type the exact tenant slug to confirm permanent deletion.'],
            ]);
        }

        $tenantId = (string) $tenant->id;
        $slug = (string) $tenant->slug;
        $name = (string) ($tenant->trading_name ?: $tenant->name);

        $this->auditLogger->log('platform.tenant.purge_requested', $tenant, null, [
            'slug' => $slug,
            'name' => $name,
        ], $actor);

        $userIds = TeamMember::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($tenantId, $userIds) {
            $this->wipeTenantScopedRows($tenantId);
            $this->deleteOrphanedTenantUsers($userIds);
            Tenant::query()->where('id', $tenantId)->delete();
        });

        $this->bestEffortClearStorage($tenantId);

        $this->auditLogger->log('platform.tenant.purged', null, null, [
            'tenant_id' => $tenantId,
            'slug' => $slug,
            'name' => $name,
        ], $actor);

        return [
            'purged' => true,
            'tenant_id' => $tenantId,
            'slug' => $slug,
            'name' => $name,
        ];
    }

    private function wipeTenantScopedRows(string $tenantId): void
    {
        $tables = $this->tablesWithTenantId();
        $remaining = $tables;
        $maxPasses = 40;

        while ($remaining !== [] && $maxPasses-- > 0) {
            $failed = [];
            foreach ($remaining as $table) {
                try {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                } catch (Throwable) {
                    $failed[] = $table;
                }
            }

            if ($failed === []) {
                return;
            }

            if (count($failed) === count($remaining)) {
                // Still blocked — one more attempt after a short shuffle of order.
                $remaining = array_reverse($failed);
                foreach ($remaining as $table) {
                    try {
                        DB::table($table)->where('tenant_id', $tenantId)->delete();
                    } catch (Throwable $e) {
                        throw ValidationException::withMessages([
                            'tenant' => [
                                'Could not delete all tenant data (blocked on table '.$table.'): '.$e->getMessage(),
                            ],
                        ]);
                    }
                }

                return;
            }

            $remaining = $failed;
        }

        if ($remaining !== []) {
            throw ValidationException::withMessages([
                'tenant' => ['Could not delete all tenant-scoped rows. Remaining tables: '.implode(', ', $remaining)],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function tablesWithTenantId(): array
    {
        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $name = is_string($table) ? $table : (string) ($table->name ?? '');
            if (str_contains($name, '.')) {
                $name = (string) substr($name, strrpos($name, '.') + 1);
            }
            if ($name === '' || $name === 'tenants') {
                continue;
            }
            if (Schema::hasColumn($name, 'tenant_id')) {
                $tables[] = $name;
            }
        }

        // Prefer wiping leaf-ish tables first by putting common parents later.
        usort($tables, function (string $a, string $b): int {
            $weight = static function (string $table): int {
                return match (true) {
                    str_contains($table, 'attempt') => 10,
                    str_contains($table, 'item') => 20,
                    str_contains($table, 'message') => 30,
                    str_contains($table, 'appointment') => 40,
                    str_contains($table, 'client') => 50,
                    str_contains($table, 'team_member') => 80,
                    str_contains($table, 'location') => 85,
                    str_contains($table, 'workspace') => 90,
                    str_contains($table, 'subscription') => 95,
                    default => 60,
                };
            };

            return $weight($a) <=> $weight($b) ?: strcmp($a, $b);
        });

        return $tables;
    }

    /**
     * @param  list<string|int>  $userIds
     */
    private function deleteOrphanedTenantUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $id = (string) $userId;
            if ($id === '') {
                continue;
            }

            $stillLinked = TeamMember::withoutGlobalScopes()
                ->where('user_id', $id)
                ->exists();
            if ($stillLinked) {
                continue;
            }

            $user = User::query()->find($id);
            if ($user === null || $user->is_platform_admin) {
                continue;
            }

            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $id)
                    ->delete();
            }

            if (Schema::hasTable('auth_action_tokens')) {
                DB::table('auth_action_tokens')->where('user_id', $id)->delete();
            }

            $user->delete();
        }
    }

    private function bestEffortClearStorage(string $tenantId): void
    {
        foreach ([
            "tenants/{$tenantId}",
            "branding/{$tenantId}",
            "gallery/{$tenantId}",
            "lookbook/{$tenantId}",
        ] as $directory) {
            try {
                Storage::disk('public')->deleteDirectory($directory);
            } catch (Throwable) {
                // best-effort
            }
        }
    }
}
