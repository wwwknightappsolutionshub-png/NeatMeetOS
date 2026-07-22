<?php

namespace App\Domains\Memberships\Enums;

final class ClientPackageStatus
{
    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const DEPLETED = 'depleted';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [self::ACTIVE, self::EXPIRED, self::DEPLETED, self::CANCELLED];
    }
}
