<?php

namespace App\Domains\Inventory\Enums;

final class ConsumptionMode
{
    public const FIXED = 'fixed';

    public const OPTIONAL = 'optional';

    public const ESTIMATED = 'estimated';

    public static function all(): array
    {
        return [self::FIXED, self::OPTIONAL, self::ESTIMATED];
    }
}
