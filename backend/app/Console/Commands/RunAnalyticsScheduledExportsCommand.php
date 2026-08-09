<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Services\AnalyticsScheduledExportService;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

class RunAnalyticsScheduledExportsCommand extends Command
{
    protected $signature = 'analytics:run-scheduled
                            {--tenant= : Limit to a single tenant UUID}';

    protected $description = 'Dispatch due scheduled analytics saved-report exports';

    public function handle(
        TenantContext $tenantContext,
        AnalyticsScheduledExportService $scheduledExports,
    ): int {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('tenant')) {
            $query->where('id', $this->option('tenant'));
        }

        $tenants = $query->get();
        $this->info('Running analytics scheduled exports for '.$tenants->count().' tenant(s).');

        foreach ($tenants as $tenant) {
            $tenantContext->set($tenant);

            try {
                $summary = $scheduledExports->runDue();
                $this->line(sprintf(
                    '[%s] dispatched=%d skipped=%d',
                    $tenant->slug ?? $tenant->id,
                    $summary['dispatched'],
                    $summary['skipped'],
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
