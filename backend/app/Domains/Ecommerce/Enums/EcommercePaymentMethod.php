<?php

namespace App\Domains\Ecommerce\Enums;

final class EcommercePaymentMethod
{
    public const CASH_IN_SALON = 'cash_in_salon';

    public static function all(): array
    {
        return [self::CASH_IN_SALON];
    }
}
