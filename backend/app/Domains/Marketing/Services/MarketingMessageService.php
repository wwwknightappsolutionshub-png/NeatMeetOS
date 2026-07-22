<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Models\MarketingRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MarketingMessageService
{
    public function __construct(
        private readonly MarketingScopeValidator $scope,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketingMessage::query()
            ->with(['client', 'attempts'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['workflow_execution_id'])) {
            $query->where('workflow_execution_id', $filters['workflow_execution_id']);
        }

        return $query->paginate($perPage);
    }

    public function listForRun(MarketingRun $run, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $this->scope->assertTenantModel($run);

        $query = MarketingMessage::query()
            ->with(['client', 'appointment', 'membership', 'location'])
            ->where('marketing_run_id', $run->id)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): MarketingMessage
    {
        return $this->scope->findMessage($id)->load(['client', 'appointment', 'attempts']);
    }
}
