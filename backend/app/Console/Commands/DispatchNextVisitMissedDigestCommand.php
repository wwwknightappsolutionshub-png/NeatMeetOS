<?php

namespace App\Console\Commands;

use App\Domains\Crm\Services\NextVisitMissedDigestService;
use Illuminate\Console\Command;

class DispatchNextVisitMissedDigestCommand extends Command
{
    protected $signature = 'next-visit:missed-digest';

    protected $description = 'Send end-of-day owner digests for check-ins without a next visit booking';

    public function handle(NextVisitMissedDigestService $digest): int
    {
        $result = $digest->dispatchForAllTenants();

        $this->info(sprintf(
            'Processed %d tenant(s) in EOD window; sent %d digest(s) covering %d client(s).',
            $result['tenants'],
            $result['digests'],
            $result['clients'],
        ));

        return self::SUCCESS;
    }
}
