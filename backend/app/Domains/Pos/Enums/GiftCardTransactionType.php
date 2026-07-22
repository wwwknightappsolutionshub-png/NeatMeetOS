<?php

namespace App\Domains\Pos\Enums;

final class GiftCardTransactionType
{
    public const ISSUE = 'issue';

    public const REDEEM = 'redeem';

    public const REFUND_RESTORE = 'refund_restore';

    public const ADJUSTMENT = 'adjustment';

    public const VOID = 'void';

    public static function all(): array
    {
        return [self::ISSUE, self::REDEEM, self::REFUND_RESTORE, self::ADJUSTMENT, self::VOID];
    }
}
