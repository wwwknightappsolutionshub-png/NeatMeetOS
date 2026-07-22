<?php

namespace App\Domains\Ecommerce\Enums;

final class EcommerceProductStatus
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::ARCHIVED];
    }
}
