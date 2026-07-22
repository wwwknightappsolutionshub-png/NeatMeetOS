<?php

namespace App\Domains\Payments\Enums;

final class PaymentTransactionType
{
    public const DEPOSIT = 'deposit';

    public const SALE = 'sale';

    public const MEMBERSHIP = 'membership';

    public const GIFT_CARD = 'gift_card';

    public const REFUND = 'refund';

    public const ADJUSTMENT = 'adjustment';

    public static function all(): array
    {
        return [
            self::DEPOSIT,
            self::SALE,
            self::MEMBERSHIP,
            self::GIFT_CARD,
            self::REFUND,
            self::ADJUSTMENT,
        ];
    }
}
