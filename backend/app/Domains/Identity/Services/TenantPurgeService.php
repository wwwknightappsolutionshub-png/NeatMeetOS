<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        try {
            DB::transaction(function () use ($tenantId, $userIds) {
                $this->breakCircularForeignKeys($tenantId);
                $this->wipeBillingDependencies($tenantId);
                $this->wipeTenantScopedRows($tenantId);
                $this->wipeAlternateTenantReferences($tenantId);
                $this->deleteOrphanedTenantUsers($userIds);

                // Hard delete — never soft-delete; row must leave the table.
                DB::table('tenants')->where('id', $tenantId)->delete();

                if (DB::table('tenants')->where('id', $tenantId)->exists()) {
                    throw ValidationException::withMessages([
                        'tenant' => ['Tenant row could not be removed. Check database foreign keys.'],
                    ]);
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('platform.tenant.purge_failed', [
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'tenant' => ['Permanent delete failed: '.$e->getMessage()],
            ]);
        }

        $this->bestEffortClearStorage($tenantId);

        try {
            $this->auditLogger->log('platform.tenant.purged', null, null, [
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'name' => $name,
            ], $actor);
        } catch (Throwable $e) {
            Log::warning('platform.tenant.purge_audit_failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'purged' => true,
            'tenant_id' => $tenantId,
            'slug' => $slug,
            'name' => $name,
        ];
    }

    /**
     * Nullify known cross-table cycles so multi-pass deletes succeed when
     * session_replication_role / FOREIGN_KEY_CHECKS cannot be enabled.
     */
    private function breakCircularForeignKeys(string $tenantId): void
    {
        $nullableColumns = [
            'appointments' => ['origin_visit_id', 'rebooked_from_appointment_id'],
            'client_visits' => ['next_visit_appointment_id'],
            'payment_transactions' => ['appointment_id'],
            'payment_allocations' => ['appointment_id'],
            'waitlist_entries' => ['fulfilled_appointment_id'],
        ];

        foreach ($nullableColumns as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $updates = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $updates[$column] = null;
                }
            }
            if ($updates !== []) {
                DB::table($table)->where('tenant_id', $tenantId)->update($updates);
            }
        }
    }

    /**
     * Clear referral rows that reference the tenant without a matching tenant_id column wipe.
     */
    private function wipeAlternateTenantReferences(string $tenantId): void
    {
        $pairs = [
            ['platform_referral_invites', 'referrer_tenant_id'],
            ['platform_referral_conversions', 'referrer_tenant_id'],
            ['platform_referral_conversions', 'referred_tenant_id'],
        ];

        foreach ($pairs as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $this->runInSavepoint('alt_'.substr(md5($table.$column), 0, 12), function () use ($table, $column, $tenantId) {
                DB::table($table)->where($column, $tenantId)->delete();
            });
        }
    }

    /**
     * Platform billing FKs: invoice attempts → invoices → tenant_subscriptions.
     */
    private function wipeBillingDependencies(string $tenantId): void
    {
        foreach ([
            'platform_invoice_attempts',
            'platform_invoices',
            'tenant_subscriptions',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $this->deleteTenantScopedTable($table, $tenantId);
        }

        // Invoices may keep a subscription FK; nullify before any leftover subscription wipe.
        if (Schema::hasTable('platform_invoices') && Schema::hasColumn('platform_invoices', 'tenant_subscription_id')) {
            $this->runInSavepoint('null_invoice_sub', function () use ($tenantId) {
                DB::table('platform_invoices')
                    ->where('tenant_id', $tenantId)
                    ->update(['tenant_subscription_id' => null]);
            });
        }
    }

    private function wipeTenantScopedRows(string $tenantId): void
    {
        $tables = $this->tablesWithTenantId();
        $driver = DB::connection()->getDriverName();
        $fkDisabled = false;

        try {
            if ($driver === 'pgsql') {
                try {
                    DB::statement('SET LOCAL session_replication_role = replica');
                    $fkDisabled = true;
                } catch (Throwable) {
                    $fkDisabled = false;
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                $fkDisabled = true;
            }

            if ($fkDisabled) {
                foreach ($tables as $table) {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }

                return;
            }

            $remaining = $tables;
            $maxPasses = 40;
            /** @var array<string, string> $lastErrors */
            $lastErrors = [];

            while ($remaining !== [] && $maxPasses-- > 0) {
                $failed = [];
                foreach ($remaining as $table) {
                    $error = $this->deleteTenantScopedTable($table, $tenantId);
                    if ($error !== null) {
                        $failed[] = $table;
                        $lastErrors[$table] = $error;
                    } else {
                        unset($lastErrors[$table]);
                    }
                }

                if ($failed === []) {
                    return;
                }

                if (count($failed) === count($remaining)) {
                    // Final reverse pass with the first real error message (not 25P02 noise).
                    $remaining = array_reverse($failed);
                    foreach ($remaining as $table) {
                        $error = $this->deleteTenantScopedTable($table, $tenantId);
                        if ($error !== null) {
                            throw ValidationException::withMessages([
                                'tenant' => [
                                    'Could not delete all tenant data (blocked on table '.$table.'): '.$error,
                                ],
                            ]);
                        }
                    }

                    return;
                }

                $remaining = $failed;
            }

            if ($remaining !== []) {
                $detail = implode(', ', array_map(
                    static fn (string $table): string => $table.(isset($lastErrors[$table]) ? ' ('.$lastErrors[$table].')' : ''),
                    $remaining,
                ));
                throw ValidationException::withMessages([
                    'tenant' => ['Could not delete all tenant-scoped rows. Remaining tables: '.$detail],
                ]);
            }
        } finally {
            if ($fkDisabled) {
                try {
                    if ($driver === 'pgsql') {
                        DB::statement('SET LOCAL session_replication_role = DEFAULT');
                    } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                        DB::statement('SET FOREIGN_KEY_CHECKS=1');
                    }
                } catch (Throwable) {
                    // best-effort restore
                }
            }
        }
    }

    /**
     * Delete tenant-scoped rows. On PostgreSQL, wrap in a SAVEPOINT so a FK
     * failure does not abort the outer transaction (SQLSTATE 25P02).
     *
     * @return string|null Error message when delete failed; null on success
     */
    private function deleteTenantScopedTable(string $table, string $tenantId): ?string
    {
        return $this->runInSavepoint('purge_'.substr(md5($table), 0, 16), function () use ($table, $tenantId) {
            DB::table($table)->where('tenant_id', $tenantId)->delete();
        });
    }

    /**
     * @param  callable(): void  $callback
     * @return string|null Error message when failed; null on success
     */
    private function runInSavepoint(string $name, callable $callback): ?string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'purge_sp';
        $driver = DB::connection()->getDriverName();
        $supportsSavepoints = in_array($driver, ['pgsql', 'mysql', 'mariadb', 'sqlite'], true);

        if ($supportsSavepoints) {
            try {
                DB::statement('SAVEPOINT '.$safe);
            } catch (Throwable) {
                $supportsSavepoints = false;
            }
        }

        try {
            $callback();
            if ($supportsSavepoints) {
                try {
                    DB::statement('RELEASE SAVEPOINT '.$safe);
                } catch (Throwable) {
                    // ignore
                }
            }

            return null;
        } catch (Throwable $e) {
            if ($supportsSavepoints) {
                try {
                    DB::statement('ROLLBACK TO SAVEPOINT '.$safe);
                } catch (Throwable) {
                    // ignore
                }
            }

            return $e->getMessage();
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
            // Strip Postgres quoting if present.
            $name = trim($name, '"');
            if ($name === '' || $name === 'tenants') {
                continue;
            }
            if (Schema::hasColumn($name, 'tenant_id')) {
                $tables[] = $name;
            }
        }

        usort($tables, function (string $a, string $b): int {
            $weight = static function (string $table): int {
                return match (true) {
                    str_contains($table, 'invoice_attempt') => 5,
                    str_contains($table, 'attempt') => 10,
                    str_contains($table, 'invoice') => 15,
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
