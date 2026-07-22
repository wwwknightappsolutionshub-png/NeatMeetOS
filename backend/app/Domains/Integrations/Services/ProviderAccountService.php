<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderAccountStatus;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderAccountService
{
    public function __construct(
        private readonly IntegrationsScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly ProviderDriverCompatibility $compatibility,
        private readonly ProviderCredentialValidator $credentialValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ProviderAccount>
     */
    public function list(array $filters = []): Collection
    {
        $query = ProviderAccount::query()
            ->with(['createdBy', 'updatedBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('archived', $filters) && $filters['archived'] !== null) {
            $archived = filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN);
            $query->{$archived ? 'whereNotNull' : 'whereNull'}('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        return $query->get();
    }

    public function find(string $id): ProviderAccount
    {
        return $this->scope->findProviderAccount($id)->load(['createdBy', 'updatedBy']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $teamMemberId = null): ProviderAccount
    {
        $payload = $this->normalize($data);

        return DB::transaction(function () use ($payload, $teamMemberId, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefaultForCategory($payload['category']);
            }

            $account = ProviderAccount::query()->create(array_merge($payload, [
                'tenant_id' => $this->scope->tenantId(),
                'created_by_team_member_id' => $teamMemberId,
                'updated_by_team_member_id' => $teamMemberId,
            ]));

            $this->auditLogger->log('provider_account.created', $account, null, $account->only([
                'name', 'category', 'driver', 'status',
            ]));

            return $account->fresh(['createdBy', 'updatedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProviderAccount $account, array $data, ?string $teamMemberId = null): ProviderAccount
    {
        $payload = $this->normalize($data, partial: true);

        return DB::transaction(function () use ($account, $payload, $teamMemberId, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefaultForCategory($account->category, exceptId: $account->id);
            }

            $category = $payload['category'] ?? $account->category;
            $driver = $payload['driver'] ?? $account->driver;
            if (isset($payload['category']) || isset($payload['driver'])) {
                $this->compatibility->assertCompatible($category, $driver);
            }

            $old = $account->only(array_keys($payload));
            $account->fill($payload);
            $account->updated_by_team_member_id = $teamMemberId;
            $account->save();

            $this->auditLogger->log('provider_account.updated', $account, $old, $account->only(array_keys($payload)));

            return $account->fresh(['createdBy', 'updatedBy']);
        });
    }

    public function activate(ProviderAccount $account, ?string $teamMemberId = null): ProviderAccount
    {
        return $this->setStatus($account, ProviderAccountStatus::ACTIVE, 'provider_account.activated', $teamMemberId);
    }

    public function deactivate(ProviderAccount $account, ?string $teamMemberId = null): ProviderAccount
    {
        return DB::transaction(function () use ($account, $teamMemberId) {
            if ($account->is_default) {
                $account->is_default = false;
            }

            return $this->setStatus($account, ProviderAccountStatus::INACTIVE, 'provider_account.deactivated', $teamMemberId);
        });
    }

    public function archive(ProviderAccount $account, ?string $teamMemberId = null): ProviderAccount
    {
        return DB::transaction(function () use ($account, $teamMemberId) {
            if ($account->archived_at === null) {
                $account->archived_at = now();
                $account->status = ProviderAccountStatus::ARCHIVED;
                $account->is_default = false;
                $account->updated_by_team_member_id = $teamMemberId;
                $account->save();
            }

            $this->auditLogger->log('provider_account.archived', $account, null, [
                'archived_at' => $account->archived_at?->toIso8601String(),
            ]);

            return $account->fresh(['createdBy', 'updatedBy']);
        });
    }

    public function setDefault(ProviderAccount $account, ?string $teamMemberId = null): ProviderAccount
    {
        if ($account->isArchived() || ! in_array($account->status, ProviderAccountStatus::dispatchable(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Only active provider accounts can be set as default.'],
            ]);
        }

        return DB::transaction(function () use ($account, $teamMemberId) {
            $this->clearDefaultForCategory($account->category, exceptId: $account->id);

            $account->is_default = true;
            $account->updated_by_team_member_id = $teamMemberId;
            $account->save();

            $this->auditLogger->log('provider_account.updated', $account, ['is_default' => false], ['is_default' => true]);

            return $account->fresh(['createdBy', 'updatedBy']);
        });
    }

    public function testConnection(ProviderAccount $account, ?string $teamMemberId = null): ProviderAccount
    {
        $result = match (true) {
            in_array($account->driver, [ProviderDriver::SIMULATION, ProviderDriver::MANUAL], true) => 'simulation_ok',
            ! $this->compatibility->isCompatible($account->category, $account->driver) => 'category_driver_mismatch',
            ! $this->credentialValidator->validate($account)['valid'] => 'credentials_missing',
            default => 'credentials_valid_stub',
        };

        $account->last_tested_at = now();
        $account->last_test_result = $result;
        $account->updated_by_team_member_id = $teamMemberId;
        $account->save();

        $this->auditLogger->log('provider_account.tested', $account, null, [
            'last_test_result' => $result,
        ]);

        return $account->fresh(['createdBy', 'updatedBy']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $payload = [];

        foreach (['name', 'category', 'driver', 'status', 'from_name', 'from_address', 'reply_to', 'phone_number', 'webhook_secret'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            } elseif (! $partial) {
                if (in_array($field, ['name', 'category', 'driver'], true)) {
                    throw ValidationException::withMessages([$field => ["The {$field} field is required."]]);
                }
            }
        }

        if (array_key_exists('configuration', $data)) {
            $payload['configuration_json'] = $data['configuration'];
        }

        if (array_key_exists('credentials', $data)) {
            $payload['credentials_json'] = $data['credentials'];
        }

        if (array_key_exists('metadata', $data)) {
            $payload['metadata_json'] = $data['metadata'];
        }

        if (array_key_exists('is_default', $data)) {
            $payload['is_default'] = (bool) $data['is_default'];
        }

        if (! $partial && ! isset($payload['status'])) {
            $payload['status'] = ProviderAccountStatus::ACTIVE;
        }

        if (! $partial && ! isset($payload['driver'])) {
            $payload['driver'] = ProviderDriver::SIMULATION;
        }

        if (isset($payload['category']) && ! in_array($payload['category'], ProviderCategory::all(), true)) {
            throw ValidationException::withMessages(['category' => ['Invalid provider category.']]);
        }

        if (isset($payload['driver']) && ! in_array($payload['driver'], ProviderDriver::all(), true)) {
            throw ValidationException::withMessages(['driver' => ['Invalid provider driver.']]);
        }

        if (isset($payload['category'], $payload['driver'])) {
            $this->compatibility->assertCompatible($payload['category'], $payload['driver']);
        }

        return $payload;
    }

    private function clearDefaultForCategory(string $category, ?string $exceptId = null): void
    {
        $query = ProviderAccount::query()
            ->where('category', $category)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    private function setStatus(
        ProviderAccount $account,
        string $status,
        string $auditAction,
        ?string $teamMemberId,
    ): ProviderAccount {
        $old = ['status' => $account->status];
        $account->status = $status;
        $account->updated_by_team_member_id = $teamMemberId;
        $account->save();

        $this->auditLogger->log($auditAction, $account, $old, ['status' => $status]);

        return $account->fresh(['createdBy', 'updatedBy']);
    }
}
