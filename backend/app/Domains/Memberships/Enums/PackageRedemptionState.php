<?php

namespace App\Domains\Memberships\Enums;

final class PackageRedemptionState
{
    public const RESERVED = 'reserved';

    public const REDEEMED = 'redeemed';

    public const RESTORED = 'restored';

    public const RELEASED = 'released';

    public static function all(): array
    {
        return [self::RESERVED, self::REDEEMED, self::RESTORED, self::RELEASED];
    }
}
