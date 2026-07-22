<?php

namespace App\Domains\Inventory\Enums;

final class InventoryItemStatus
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::ARCHIVED];
    }
}
