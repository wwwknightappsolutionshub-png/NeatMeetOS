<?php

namespace App\Console\Commands;

use App\Jobs\SendTenantWorkspaceWelcomeJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Queue workspace welcome email + WhatsApp for new tenants after deploy.
 */
class QueueTenantWorkspaceWelcomesCommand extends Command
{
    protected $signature = 'tenants:queue-workspace-welcomes
                            {--delay=5 : Minutes to wait before sending (when no --at or config scheduled_at)}
                            {--at= : Absolute send time (Y-m-d H:i:s) in scheduled_timezone}
                            {--dry-run : Print recipients without queueing}';

    protected $description = 'Queue workspace welcome email + WhatsApp for configured new tenants';

    public function handle(): int
    {
        $sendAt = $this->resolveSendAt();
        if ($sendAt === null) {
            return self::FAILURE;
        }

        /** @var list<array{email: string, phone: string}> $recipients */
        $recipients = config('post_deploy_welcomes.recipients', []);

        if ($recipients === []) {
            $this->warn('No recipients in config/post_deploy_welcomes.php');

            return self::SUCCESS;
        }

        $timezone = (string) config('post_deploy_welcomes.scheduled_timezone', config('app.timezone', 'UTC'));
        $this->line("Send at: {$sendAt->timezone($timezone)->toDateTimeString()} ({$timezone})");

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            $phone = trim((string) ($recipient['phone'] ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid email in config: {$email}");

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $this->line("Would queue {$email} ({$phone})");

                continue;
            }

            SendTenantWorkspaceWelcomeJob::dispatch($email, $phone !== '' ? $phone : null)
                ->delay($sendAt);
            $this->info("Queued workspace welcome for {$email}");
        }

        if (! $this->option('dry-run')) {
            $this->comment('Ensure neatmeet-queue is running so delayed jobs are processed.');
        }

        return self::SUCCESS;
    }

    private function resolveSendAt(): ?Carbon
    {
        $timezone = (string) config('post_deploy_welcomes.scheduled_timezone', config('app.timezone', 'UTC'));
        $atOption = trim((string) $this->option('at'));
        $atConfig = trim((string) config('post_deploy_welcomes.scheduled_at', ''));

        try {
            if ($atOption !== '') {
                $sendAt = Carbon::parse($atOption, $timezone);
            } elseif ($atConfig !== '') {
                $sendAt = Carbon::parse($atConfig, $timezone);
            } else {
                $delayMinutes = max(0, (int) $this->option('delay'));
                $sendAt = now($timezone)->addMinutes($delayMinutes);
            }
        } catch (\Throwable $e) {
            $this->error('Invalid send time: '.$e->getMessage());

            return null;
        }

        if ($sendAt->isPast()) {
            $this->error('Send time is in the past. Use --at with a future datetime or update config/post_deploy_welcomes.php.');

            return null;
        }

        return $sendAt;
    }
}
