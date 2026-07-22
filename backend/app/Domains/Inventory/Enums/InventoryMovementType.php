<?php

namespace App\Domains\Inventory\Enums;

final class InventoryMovementType
{
    public const OPENING = 'opening';

    public const ADJUSTMENT = 'adjustment';

    public const PURCHASE_RECEIPT = 'purchase_receipt';

    public const SALE = 'sale';

    public const SERVICE_CONSUMPTION = 'service_consumption';

    public const WASTE = 'waste';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';

    public static function all(): array
    {
        return [
            self::OPENING,
            self::ADJUSTMENT,
            self::PURCHASE_RECEIPT,
            self::SALE,
            self::SERVICE_CONSUMPTION,
            self::WASTE,
            self::TRANSFER_IN,
            self::TRANSFER_OUT,
        ];
    }

    public static function manualTypes(): array
    {
        return [
            self::OPENING,
            self::ADJUSTMENT,
            self::PURCHASE_RECEIPT,
            self::WASTE,
            self::TRANSFER_IN,
            self::TRANSFER_OUT,
        ];
    }
}
