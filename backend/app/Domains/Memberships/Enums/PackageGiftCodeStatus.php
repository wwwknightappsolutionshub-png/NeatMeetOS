<?php

namespace App\Domains\Memberships\Enums;

final class PackageGiftCodeStatus
{
    public const OPEN = 'open';

    public const CLAIMED = 'claimed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [self::OPEN, self::CLAIMED, self::CANCELLED, self::EXPIRED];
    }
}
