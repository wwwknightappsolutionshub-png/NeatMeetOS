<?php

namespace App\Domains\Memberships\Enums;

final class WalletEntryType
{
    public const MANUAL_CREDIT = 'manual_credit';

    public const MANUAL_DEBIT = 'manual_debit';

    public const MEMBERSHIP_CREDIT = 'membership_credit';

    public const PACKAGE_CREDIT = 'package_credit';

    public const REFUND_CREDIT = 'refund_credit';

    public const POS_REDEMPTION = 'pos_redemption';

    public const ADJUSTMENT = 'adjustment';

    public static function all(): array
    {
        return [
            self::MANUAL_CREDIT,
            self::MANUAL_DEBIT,
            self::MEMBERSHIP_CREDIT,
            self::PACKAGE_CREDIT,
            self::REFUND_CREDIT,
            self::POS_REDEMPTION,
            self::ADJUSTMENT,
        ];
    }
}
