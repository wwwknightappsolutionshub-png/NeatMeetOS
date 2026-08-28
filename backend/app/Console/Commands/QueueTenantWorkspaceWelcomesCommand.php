<?php

namespace App\Console\Commands;

use App\Jobs\SendTenantWorkspaceWelcomeJob;
use Illuminate\Console\Command;

/**
 * Queue workspace welcome email + WhatsApp for new tenants after deploy.
 */
class QueueTenantWorkspaceWelcomesCommand extends Command
{
    protected $signature = 'tenants:queue-workspace-welcomes
                            {--delay=5 : Minutes to wait before sending}
                            {--dry-run : Print recipients without queueing}';

    protected $description = 'Queue workspace welcome email + WhatsApp for configured new tenants';

    public function handle(): int
    {
        $delayMinutes = max(0, (int) $this->option('delay'));
        /** @var list<array{email: string, phone: string}> $recipients */
        $recipients = config('post_deploy_welcomes.recipients', []);
        $sendAt = now()->addMinutes($delayMinutes);

        if ($recipients === []) {
            $this->warn('No recipients in config/post_deploy_welcomes.php');

            return self::SUCCESS;
        }

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            $phone = trim((string) ($recipient['phone'] ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid email in config: {$email}");

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $this->line("Would queue {$email} ({$phone}) at {$sendAt->toDateTimeString()}");

                continue;
            }

            SendTenantWorkspaceWelcomeJob::dispatch($email, $phone !== '' ? $phone : null)
                ->delay($sendAt);
            $this->info("Queued workspace welcome for {$email} at {$sendAt->toDateTimeString()}");
        }

        if (! $this->option('dry-run')) {
            $this->comment('Ensure neatmeet-queue is running so delayed jobs are processed.');
        }

        return self::SUCCESS;
    }
}
