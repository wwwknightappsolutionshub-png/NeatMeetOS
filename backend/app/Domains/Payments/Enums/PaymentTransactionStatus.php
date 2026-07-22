<?php

namespace App\Domains\Payments\Enums;

final class PaymentTransactionStatus
{
    public const PENDING = 'pending';

    public const AUTHORIZED = 'authorized';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::AUTHORIZED,
            self::SUCCEEDED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
        ];
    }
}
