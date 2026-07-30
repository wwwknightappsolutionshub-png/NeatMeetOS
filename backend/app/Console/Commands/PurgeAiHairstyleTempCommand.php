<?php

namespace App\Console\Commands;

use App\Domains\AiHairstyle\Services\AiHairstyleTempCleanupService;
use Illuminate\Console\Command;

class PurgeAiHairstyleTempCommand extends Command
{
    protected $signature = 'ai-hairstyle:purge-temp {--minutes= : Override max age in minutes}';

    protected $description = 'Delete orphaned AI hairstyle ephemeral selfies older than the retention window';

    public function handle(AiHairstyleTempCleanupService $cleanup): int
    {
        $minutes = $this->option('minutes');
        $result = $cleanup->purgeStale(
            $minutes !== null && $minutes !== '' ? (int) $minutes : null
        );

        $this->info(sprintf(
            'Scanned %d temp file(s); deleted %d stale selfie(s).',
            $result['scanned'],
            $result['deleted'],
        ));

        return self::SUCCESS;
    }
}
