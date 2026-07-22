<?php

namespace App\Shared\Commerce\Enums;

final class SaleLineType
{
    public const APPOINTMENT_SERVICE = 'appointment_service';

    public const RETAIL_PRODUCT = 'retail_product';

    public const DEPOSIT_CREDIT = 'deposit_credit';

    public const DISCOUNT = 'discount';

    public const TIP = 'tip';

    public const TAX = 'tax';

    public const PACKAGE_REDEMPTION = 'package_redemption';

    public const GIFT_CARD_SALE = 'gift_card_sale';

    public const GIFT_CARD_REDEMPTION = 'gift_card_redemption';

    public const MEMBERSHIP_DISCOUNT = 'membership_discount';

    public const SERVICE_FEE = 'service_fee';

    public static function all(): array
    {
        return [
            self::APPOINTMENT_SERVICE,
            self::RETAIL_PRODUCT,
            self::DEPOSIT_CREDIT,
            self::DISCOUNT,
            self::TIP,
            self::TAX,
            self::PACKAGE_REDEMPTION,
            self::GIFT_CARD_SALE,
            self::GIFT_CARD_REDEMPTION,
            self::MEMBERSHIP_DISCOUNT,
            self::SERVICE_FEE,
        ];
    }

    public static function isRevenueLine(string $type): bool
    {
        return in_array($type, [self::APPOINTMENT_SERVICE, self::RETAIL_PRODUCT, self::SERVICE_FEE, self::GIFT_CARD_SALE], true);
    }
}
