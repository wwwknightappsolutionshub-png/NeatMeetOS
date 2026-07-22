<?php

namespace App\Domains\Pos\Enums;

final class GiftCardStatus
{
    public const ACTIVE = 'active';

    public const REDEEMED = 'redeemed';

    public const EXPIRED = 'expired';

    public const VOIDED = 'voided';

    public static function all(): array
    {
        return [self::ACTIVE, self::REDEEMED, self::EXPIRED, self::VOIDED];
    }
}
