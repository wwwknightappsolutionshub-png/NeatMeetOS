<?php

namespace App\Domains\Inventory\Enums;

final class MovementReferenceType
{
    public const APPOINTMENT = 'appointment';

    public const CHECKOUT = 'checkout';

    public const MANUAL = 'manual';

    public const SUPPLIER = 'supplier';

    public const STOCKTAKE = 'stocktake';

    public const TRANSFER = 'transfer';

    public const ECOMMERCE_ORDER = 'ecommerce_order';

    public static function all(): array
    {
        return [
            self::APPOINTMENT,
            self::CHECKOUT,
            self::MANUAL,
            self::SUPPLIER,
            self::STOCKTAKE,
            self::TRANSFER,
            self::ECOMMERCE_ORDER,
        ];
    }
}
