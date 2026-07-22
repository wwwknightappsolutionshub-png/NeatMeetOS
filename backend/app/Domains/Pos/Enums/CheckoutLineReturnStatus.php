<?php

namespace App\Domains\Pos\Enums;

final class CheckoutLineReturnStatus
{
    public const NOT_RETURNED = 'not_returned';

    public const PARTIALLY_RETURNED = 'partially_returned';

    public const RETURNED = 'returned';

    public static function all(): array
    {
        return [self::NOT_RETURNED, self::PARTIALLY_RETURNED, self::RETURNED];
    }
}
