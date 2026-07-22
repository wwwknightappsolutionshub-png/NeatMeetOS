<?php

namespace App\Shared\Commerce\Enums;

final class EntitlementReferenceState
{
    public const REFERENCED = 'referenced';

    public const RESERVED = 'reserved';

    public const REDEEMED = 'redeemed';

    public const RESTORED = 'restored';

    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [
            self::REFERENCED,
            self::RESERVED,
            self::REDEEMED,
            self::RESTORED,
            self::EXPIRED,
        ];
    }
}
