<?php

namespace App\Domains\Analytics\Enums;

final class AnalyticsScheduleFrequency
{
    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::DAILY, self::WEEKLY, self::MONTHLY];
    }
}
