<?php

namespace App\Jobs;

use App\Domains\Marketing\Models\MarketingMessage;
use App\Domains\Marketing\Services\MarketingDispatchSimulationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMarketingMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $marketingMessageId,
    ) {}

    public function handle(
        TenantContext $tenantContext,
        MarketingDispatchSimulationService $dispatch,
    ): void {
        $tenant = \App\Domains\Identity\Models\Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenantContext->set($tenant);

        $message = MarketingMessage::query()->find($this->marketingMessageId);
        if ($message === null) {
            return;
        }

        $dispatch->dispatch($message);
    }
}
