<?php

namespace App\Domains\Ecommerce\Enums;

final class EcommerceOrderStatus
{
    public const PENDING_PICKUP = 'pending_pickup';

    public const COLLECTED = 'collected';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::PENDING_PICKUP,
            self::COLLECTED,
            self::CANCELLED,
        ];
    }

    public static function adminUpdatable(): array
    {
        return [self::COLLECTED, self::CANCELLED];
    }
}
