<?php

namespace App\Shared\Commerce\Enums;

final class PaymentAllocationType
{
    public const DEPOSIT = 'deposit';

    public const CHECKOUT_SALE = 'checkout_sale';

    public const MEMBERSHIP_SUBSCRIPTION = 'membership_subscription';

    public const GIFT_CARD = 'gift_card';

    public const WALLET_TOP_UP = 'wallet_top_up';

    public const REFUND = 'refund';

    public static function all(): array
    {
        return [
            self::DEPOSIT,
            self::CHECKOUT_SALE,
            self::MEMBERSHIP_SUBSCRIPTION,
            self::GIFT_CARD,
            self::WALLET_TOP_UP,
            self::REFUND,
        ];
    }
}
