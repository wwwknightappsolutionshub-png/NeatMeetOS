<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\ProviderAccount;
use App\Domains\Integrations\Models\ProviderDeliveryAttempt;
use App\Domains\Integrations\Models\ProviderWebhookEvent;
use App\Shared\Tenancy\TenantContext;

class IntegrationsScopeValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantContext->id();
    }

    public function findProviderAccount(string $id): ProviderAccount
    {
        return ProviderAccount::query()->findOrFail($id);
    }

    public function findDeliveryAttempt(string $id): ProviderDeliveryAttempt
    {
        return ProviderDeliveryAttempt::query()->findOrFail($id);
    }

    public function findWebhookEvent(string $id): ProviderWebhookEvent
    {
        return ProviderWebhookEvent::query()->findOrFail($id);
    }

    public function assertTenantModel(ProviderAccount|ProviderDeliveryAttempt|ProviderWebhookEvent $model): void
    {
        if ($model->tenant_id !== $this->tenantId()) {
            abort(404);
        }
    }
}
