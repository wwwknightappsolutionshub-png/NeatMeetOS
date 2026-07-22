<?php

namespace App\Domains\Marketing\Enums;

final class MarketingExecutionStatus
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public static function all(): array
    {
        return [
            self::QUEUED,
            self::RUNNING,
            self::COMPLETED,
            self::CANCELLED,
            self::FAILED,
            self::SKIPPED,
        ];
    }

    public static function terminal(): array
    {
        return [self::COMPLETED, self::CANCELLED, self::FAILED, self::SKIPPED];
    }
}
