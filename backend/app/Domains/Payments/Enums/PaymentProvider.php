<?php

namespace App\Domains\Payments\Enums;

final class PaymentProvider
{
    public const MANUAL = 'manual';

    public const STRIPE = 'stripe';

    public const PAYMENT_LINK = 'payment_link';

    public static function all(): array
    {
        return [self::MANUAL, self::STRIPE, self::PAYMENT_LINK];
    }
}
