<?php

namespace App\Domains\Pos\Enums;

final class DiscountType
{
    public const MANUAL_AMOUNT = 'manual_amount';

    public const MANUAL_PERCENT = 'manual_percent';

    public const PROMO = 'promo';

    public const MANAGER_OVERRIDE = 'manager_override';

    public static function all(): array
    {
        return [self::MANUAL_AMOUNT, self::MANUAL_PERCENT, self::PROMO, self::MANAGER_OVERRIDE];
    }
}
