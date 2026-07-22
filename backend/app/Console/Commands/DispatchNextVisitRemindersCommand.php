<?php

namespace App\Console\Commands;

use App\Domains\Crm\Services\NextVisitReminderService;
use Illuminate\Console\Command;

class DispatchNextVisitRemindersCommand extends Command
{
    protected $signature = 'next-visit:dispatch-reminders
                            {--window=15 : Minutes either side of the 72h/24h lead time to match}';

    protected $description = 'Dispatch next-visit 72h and 24h reminders (in-app, email, Mode A wa.me deeplink metadata)';

    public function handle(NextVisitReminderService $reminders): int
    {
        $window = max(1, (int) $this->option('window'));
        $result = $reminders->dispatchForAllTenants($window);

        $this->info(sprintf(
            'Dispatched %d 72h and %d 24h next-visit reminder(s).',
            $result['sent_72h'],
            $result['sent_24h'],
        ));

        return self::SUCCESS;
    }
}
