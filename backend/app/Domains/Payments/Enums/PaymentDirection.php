<?php

namespace App\Domains\Payments\Enums;

final class PaymentDirection
{
    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    public static function all(): array
    {
        return [self::INBOUND, self::OUTBOUND];
    }
}
