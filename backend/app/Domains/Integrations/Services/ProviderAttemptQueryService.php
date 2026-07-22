<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\ProviderDeliveryAttempt;
use Illuminate\Support\Collection;

class ProviderAttemptQueryService
{
    public function __construct(
        private readonly IntegrationsScopeValidator $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ProviderDeliveryAttempt>
     */
    public function list(array $filters = []): Collection
    {
        $query = ProviderDeliveryAttempt::query()
            ->with(['providerAccount', 'relatedClient'])
            ->orderByDesc('created_at');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['source_domain'])) {
            $query->where('source_domain', $filters['source_domain']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider_account_id'])) {
            $query->where('provider_account_id', $filters['provider_account_id']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('related_client_id', $filters['client_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): ProviderDeliveryAttempt
    {
        return $this->scope->findDeliveryAttempt($id)->load([
            'providerAccount',
            'relatedClient',
            'relatedAppointment',
            'relatedPaymentTransaction',
        ]);
    }
}
