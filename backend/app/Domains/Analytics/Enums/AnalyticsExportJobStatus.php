<?php

namespace App\Domains\Analytics\Enums;

final class AnalyticsExportJobStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::PROCESSING, self::COMPLETED, self::FAILED];
    }
}
