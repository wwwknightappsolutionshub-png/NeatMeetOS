<?php

namespace App\Domains\Memberships\Enums;

final class ClientPackageSource
{
    public const MANUAL = 'manual';

    public const POS_SALE = 'pos_sale';

    public const MEMBERSHIP_BENEFIT = 'membership_benefit';

    public const MIGRATION = 'migration';

    public const ONLINE_PURCHASE = 'online_purchase';

    public const GIFT = 'gift';

    public static function all(): array
    {
        return [
            self::MANUAL,
            self::POS_SALE,
            self::MEMBERSHIP_BENEFIT,
            self::MIGRATION,
            self::ONLINE_PURCHASE,
            self::GIFT,
        ];
    }
}
