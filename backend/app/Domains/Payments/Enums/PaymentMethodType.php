<?php

namespace App\Domains\Payments\Enums;

final class PaymentMethodType
{
    public const CASH = 'cash';

    public const CARD = 'card';

    public const BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_LINK = 'payment_link';

    public const TERMINAL = 'terminal';

    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::CASH,
            self::CARD,
            self::BANK_TRANSFER,
            self::PAYMENT_LINK,
            self::TERMINAL,
            self::OTHER,
        ];
    }
}
