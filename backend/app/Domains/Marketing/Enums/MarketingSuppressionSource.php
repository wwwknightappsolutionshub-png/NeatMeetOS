<?php

namespace App\Domains\Marketing\Enums;

final class MarketingSuppressionSource
{
    public const CLIENT_ACTION = 'client_action';

    public const STAFF_ACTION = 'staff_action';

    public const SYSTEM = 'system';

    public static function all(): array
    {
        return [self::CLIENT_ACTION, self::STAFF_ACTION, self::SYSTEM];
    }
}
