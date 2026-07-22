<?php

namespace App\Domains\Inventory\Enums;

final class InventoryItemType
{
    public const RETAIL = 'retail';

    public const PROFESSIONAL = 'professional';

    public static function all(): array
    {
        return [self::RETAIL, self::PROFESSIONAL];
    }
}
