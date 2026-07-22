<?php

namespace App\Domains\Memberships\Enums;

final class MembershipBillingFrequency
{
    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    public const YEARLY = 'yearly';

    public static function all(): array
    {
        return [self::WEEKLY, self::MONTHLY, self::QUARTERLY, self::YEARLY];
    }
}
