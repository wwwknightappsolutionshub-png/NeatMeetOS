<?php

namespace App\Domains\Memberships\Enums;

final class ClientMembershipStatus
{
    public const TRIALING = 'trialing';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const PAST_DUE = 'past_due';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [
            self::TRIALING,
            self::ACTIVE,
            self::PAUSED,
            self::PAST_DUE,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }
}
