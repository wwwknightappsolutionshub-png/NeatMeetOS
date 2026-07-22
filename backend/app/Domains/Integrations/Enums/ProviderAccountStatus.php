<?php

namespace App\Domains\Integrations\Enums;

final class ProviderAccountStatus
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const ARCHIVED = 'archived';

    public const TEST_ONLY = 'test_only';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::ARCHIVED, self::TEST_ONLY];
    }

    /**
     * Statuses eligible for outbound dispatch routing.
     *
     * @return array<int, string>
     */
    public static function dispatchable(): array
    {
        return [self::ACTIVE, self::TEST_ONLY];
    }
}
