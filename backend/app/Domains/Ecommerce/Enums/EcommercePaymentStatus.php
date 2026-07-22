<?php

namespace App\Domains\Ecommerce\Enums;

final class EcommercePaymentStatus
{
    public const UNPAID = 'unpaid';

    public const PAID_AT_PICKUP = 'paid_at_pickup';

    public static function all(): array
    {
        return [self::UNPAID, self::PAID_AT_PICKUP];
    }
}
