<?php

namespace App\Domains\Marketing\Enums;

final class MarketingRunSource
{
    public const MANUAL = 'manual';

    public const SCHEDULER = 'scheduler';

    public const EVENT = 'event';

    public static function all(): array
    {
        return [self::MANUAL, self::SCHEDULER, self::EVENT];
    }
}
