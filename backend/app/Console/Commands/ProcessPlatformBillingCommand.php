<?php

namespace App\Console\Commands;

use App\Domains\Identity\Services\PlatformBillingService;
use Illuminate\Console\Command;

class ProcessPlatformBillingCommand extends Command
{
    protected $signature = 'platform:process-billing
                            {--generate : Generate invoices for ended periods}
                            {--collect : Attempt collection on due invoices}';

    protected $description = 'Generate platform invoices and process payment collection / dunning';

    public function handle(PlatformBillingService $billing): int
    {
        $generate = (bool) $this->option('generate') || (! $this->option('generate') && ! $this->option('collect'));
        $collect = (bool) $this->option('collect') || (! $this->option('generate') && ! $this->option('collect'));

        if ($generate) {
            $result = $billing->generateDueInvoices();
            $this->info('Invoices generated: '.$result['generated'].' (skipped '.$result['skipped'].')');
        }

        if ($collect) {
            $result = $billing->processDuePaymentAttempts();
            $this->info('Payment attempts: processed='.$result['processed'].' paid='.$result['paid'].' failed='.$result['failed']);
        }

        return self::SUCCESS;
    }
}
