<?php

namespace App\Domains\Pos\Enums;

final class ReceiptDeliveryMethod
{
    public const PRINT = 'print';

    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [self::PRINT, self::EMAIL, self::SMS, self::MANUAL];
    }
}
