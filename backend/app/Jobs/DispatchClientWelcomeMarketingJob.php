<?php

namespace App\Jobs;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Services\MarketingWelcomeAutomationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchClientWelcomeMarketingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $clientId,
    ) {}

    public function handle(
        TenantContext $tenantContext,
        MarketingWelcomeAutomationService $welcome,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $previous = $tenantContext->get();
        $tenantContext->set($tenant);

        try {
            $client = Client::query()->find($this->clientId);
            if ($client === null || ! $client->is_active) {
                return;
            }

            $welcome->sendWelcomeEmailNow($client);
        } finally {
            if ($previous !== null) {
                $tenantContext->set($previous);
            } else {
                $tenantContext->clear();
            }
        }
    }
}
