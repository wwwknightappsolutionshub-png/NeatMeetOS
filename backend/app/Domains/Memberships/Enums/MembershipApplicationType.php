<?php

namespace App\Domains\Memberships\Enums;

final class MembershipApplicationType
{
    public const PACKAGE = 'package';

    public const WALLET = 'wallet';

    public const LOYALTY = 'loyalty';

    public static function all(): array
    {
        return [self::PACKAGE, self::WALLET, self::LOYALTY];
    }
}
