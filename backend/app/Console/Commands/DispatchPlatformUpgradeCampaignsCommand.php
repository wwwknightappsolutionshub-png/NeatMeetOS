<?php

namespace App\Console\Commands;

use App\Domains\Identity\Services\PlatformUpgradeDispatchService;
use Illuminate\Console\Command;

class DispatchPlatformUpgradeCampaignsCommand extends Command
{
    protected $signature = 'platform:dispatch-upgrade-campaigns';

    protected $description = 'Send scheduled Basic→Pro / Pro→Diamond upgrade drip messages (day 3 / 7 / 21)';

    public function handle(PlatformUpgradeDispatchService $dispatch): int
    {
        $result = $dispatch->dispatchDue();
        $this->info("Upgrade campaign dispatch complete. sent={$result['sent']} skipped={$result['skipped']}");

        return self::SUCCESS;
    }
}
