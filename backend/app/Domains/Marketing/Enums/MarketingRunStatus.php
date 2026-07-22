<?php

namespace App\Domains\Marketing\Enums;

final class MarketingRunStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [self::PENDING, self::PROCESSING, self::COMPLETED, self::FAILED, self::CANCELLED];
    }
}
