<?php

namespace App\Shared\Commerce\Enums;

final class CheckoutStatus
{
    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const COMPLETED = 'completed';

    public const VOIDED = 'voided';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public const FULLY_REFUNDED = 'fully_refunded';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::OPEN,
            self::COMPLETED,
            self::VOIDED,
            self::PARTIALLY_REFUNDED,
            self::FULLY_REFUNDED,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::VOIDED, self::FULLY_REFUNDED], true);
    }
}
