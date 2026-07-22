<?php

namespace App\Shared\Commerce\Enums;

final class InventoryConsumptionType
{
    public const RETAIL_SALE = 'retail_sale';

    public const PROFESSIONAL_USE = 'professional_use';

    public const REVERSAL = 'reversal';

    public static function all(): array
    {
        return [self::RETAIL_SALE, self::PROFESSIONAL_USE, self::REVERSAL];
    }
}
