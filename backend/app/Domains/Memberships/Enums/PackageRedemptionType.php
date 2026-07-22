<?php

namespace App\Domains\Memberships\Enums;

final class PackageRedemptionType
{
    public const MANUAL_REDEEM = 'manual_redeem';

    public const MANUAL_RESTORE = 'manual_restore';

    public const POS_REDEEM = 'pos_redeem';

    public const BOOKING_REDEEM = 'booking_redeem';

    public const REFUND_RESTORE = 'refund_restore';

    public static function all(): array
    {
        return [
            self::MANUAL_REDEEM,
            self::MANUAL_RESTORE,
            self::POS_REDEEM,
            self::BOOKING_REDEEM,
            self::REFUND_RESTORE,
        ];
    }
}
