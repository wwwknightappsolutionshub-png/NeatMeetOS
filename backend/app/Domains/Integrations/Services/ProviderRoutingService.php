<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderAccountStatus;
use App\Domains\Integrations\Models\ProviderAccount;

class ProviderRoutingService
{
    public function __construct(
        private readonly IntegrationsScopeValidator $scope,
    ) {}

    public function resolveDefaultAccount(string $category, ?string $overrideAccountId = null): ?ProviderAccount
    {
        if ($overrideAccountId !== null) {
            $account = $this->scope->findProviderAccount($overrideAccountId);

            if (! $this->isDispatchable($account)) {
                return null;
            }

            return $account;
        }

        return ProviderAccount::query()
            ->where('category', $category)
            ->where('is_default', true)
            ->whereIn('status', ProviderAccountStatus::dispatchable())
            ->whereNull('archived_at')
            ->first();
    }

    public function isDispatchable(ProviderAccount $account): bool
    {
        if ($account->isArchived()) {
            return false;
        }

        return in_array($account->status, ProviderAccountStatus::dispatchable(), true);
    }
}
