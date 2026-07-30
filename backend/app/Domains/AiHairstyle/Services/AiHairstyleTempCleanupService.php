<?php

namespace App\Domains\AiHairstyle\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiHairstyleTempCleanupService
{
    /**
     * Delete orphaned ephemeral selfies older than the configured max age.
     *
     * @return array{scanned: int, deleted: int}
     */
    public function purgeStale(?int $maxAgeMinutes = null): array
    {
        $maxAge = $maxAgeMinutes ?? (int) config('ai_hairstyle.temp_max_age_minutes', 120);
        $diskName = (string) config('ai_hairstyle.temp_disk', 'local');
        $prefix = trim((string) config('ai_hairstyle.temp_prefix', 'ai_hairstyle_tmp'), '/');
        $disk = Storage::disk($diskName);
        $cutoff = now()->subMinutes(max(0, $maxAge))->getTimestamp();

        $scanned = 0;
        $deleted = 0;

        foreach ($disk->allFiles($prefix) as $path) {
            $scanned++;
            $modified = $disk->lastModified($path);
            if ($modified !== false && $modified <= $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Log::info('ai_hairstyle.temp_purged', [
                'scanned' => $scanned,
                'deleted' => $deleted,
                'max_age_minutes' => $maxAge,
            ]);
        }

        return ['scanned' => $scanned, 'deleted' => $deleted];
    }
}
