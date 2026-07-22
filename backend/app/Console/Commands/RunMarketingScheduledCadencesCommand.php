<?php

namespace App\Console\Commands;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Marketing\Services\MarketingScheduledCadenceService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

class RunMarketingScheduledCadencesCommand extends Command
{
    protected $signature = 'marketing:run-scheduled
                            {--tenant= : Limit to a single tenant UUID}';

    protected $description = 'Run marketing calendar cadences, birthday/win-back, and dispatch due messages';

    public function handle(
        TenantContext $tenantContext,
        MarketingScheduledCadenceService $cadences,
    ): int {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('tenant')) {
            $query->where('id', $this->option('tenant'));
        }

        $tenants = $query->get();
        $this->info('Running marketing scheduled cadences for '.$tenants->count().' tenant(s).');

        foreach ($tenants as $tenant) {
            $tenantContext->set($tenant);

            try {
                $summary = $cadences->runScheduled();
                $this->line(sprintf(
                    '[%s] pending=%d win_back_email=%s birthday_email=%s',
                    $tenant->slug ?? $tenant->id,
                    $summary['pending_dispatched'] ?? 0,
                    $summary['win_back']['email'] ?? 0,
                    $summary['birthday']['email'] ?? 0,
                ));
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenant->id}: ".$e->getMessage());
            } finally {
                $tenantContext->clear();
            }
        }

        return self::SUCCESS;
    }
}
