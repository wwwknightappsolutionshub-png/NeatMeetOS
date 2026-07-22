<?php

namespace App\Domains\Memberships\Enums;

final class MembershipPlanStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::ARCHIVED];
    }
}
