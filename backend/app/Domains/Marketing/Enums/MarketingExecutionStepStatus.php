<?php

namespace App\Domains\Marketing\Enums;

final class MarketingExecutionStepStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public static function all(): array
    {
        return [self::QUEUED, self::PROCESSING, self::COMPLETED, self::SKIPPED, self::FAILED];
    }
}
