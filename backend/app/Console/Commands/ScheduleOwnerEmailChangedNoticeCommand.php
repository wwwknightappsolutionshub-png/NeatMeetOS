<?php

namespace App\Console\Commands;

use App\Jobs\SendOwnerEmailChangedNoticeJob;
use Illuminate\Console\Command;

/**
 * Queue owner-email-changed notices to send after a short delay (post-deploy).
 */
class ScheduleOwnerEmailChangedNoticeCommand extends Command
{
    protected $signature = 'platform:schedule-email-changed-notice
                            {--delay=10 : Minutes to wait before sending}
                            {--login-email=bcindy87@yahoo.com : Email shown in the login instruction}
                            {--app-name=NeatMeet Saloon : Application name in the body}
                            {--to=* : Recipient emails (defaults to the two requested addresses)}';

    protected $description = 'Queue “email changed” congratulations notices after a delay';

    public function handle(): int
    {
        $delayMinutes = max(0, (int) $this->option('delay'));
        $loginEmail = strtolower(trim((string) $this->option('login-email')));
        $appName = trim((string) $this->option('app-name'));
        /** @var list<string> $recipients */
        $recipients = array_values(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            (array) $this->option('to'),
        )));

        if ($recipients === []) {
            $recipients = [
                'bcindy87@yahoo.com',
                'beacadmedia@gmail.com',
            ];
        }

        if ($loginEmail === '' || ! filter_var($loginEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid --login-email.');

            return self::FAILURE;
        }

        foreach ($recipients as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid recipient: {$recipient}");

                return self::FAILURE;
            }
        }

        $sendAt = now()->addMinutes($delayMinutes);

        foreach ($recipients as $recipient) {
            SendOwnerEmailChangedNoticeJob::dispatch($recipient, $loginEmail, $appName)
                ->delay($sendAt);
            $this->info("Queued notice for {$recipient} at {$sendAt->toDateTimeString()}");
        }

        $this->comment('Ensure neatmeet-queue is running so delayed jobs are processed.');

        return self::SUCCESS;
    }
}
