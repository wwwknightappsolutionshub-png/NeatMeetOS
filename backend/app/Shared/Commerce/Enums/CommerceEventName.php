<?php

namespace App\Shared\Commerce\Enums;

final class CommerceEventName
{
    public const DEPOSIT_REQUIRED = 'deposit.required';

    public const DEPOSIT_COLLECTED = 'deposit.collected';

    public const DEPOSIT_REFUNDED = 'deposit.refunded';

    public const DEPOSIT_FORFEITED = 'deposit.forfeited';

    public const DEPOSIT_APPLIED = 'deposit.applied';

    public const DEPOSIT_WAIVED = 'deposit.waived';

    public const DEPOSIT_FAILED = 'deposit.failed';

    public const CHECKOUT_CREATED = 'checkout.created';

    public const CHECKOUT_COMPLETED = 'checkout.completed';

    public const CHECKOUT_VOIDED = 'checkout.voided';

    public const CHECKOUT_REOPENED = 'checkout.reopened';

    public const PAYMENT_CAPTURED = 'payment.captured';

    public const PAYMENT_RECORDED = 'payment.recorded';

    public const PAYMENT_REFUNDED = 'payment.refunded';

    public const REFUND_COMPLETED = 'refund.completed';

    public const PACKAGE_RESERVED = 'package.reserved';

    public const PACKAGE_REDEEMED = 'package.redeemed';

    public const PACKAGE_RESTORED = 'package.restored';

    public const WALLET_REDEEMED = 'wallet.redeemed';

    public const WALLET_RESTORED = 'wallet.restored';

    public const LOYALTY_REDEEMED = 'loyalty.redeemed';

    public const LOYALTY_RESTORED = 'loyalty.restored';

    public const MEMBERSHIP_DISCOUNT_APPLIED = 'membership.discount_applied';

    public const STOCK_CONSUMED = 'stock.consumed';

    public const STOCK_REVERSED = 'stock.reversed';

    public const STOCK_ADJUSTED = 'stock.adjusted';

    public const STOCK_RESTOCKED = 'stock.restocked';

    public static function all(): array
    {
        return [
            self::DEPOSIT_REQUIRED,
            self::DEPOSIT_COLLECTED,
            self::DEPOSIT_REFUNDED,
            self::DEPOSIT_FORFEITED,
            self::DEPOSIT_APPLIED,
            self::DEPOSIT_WAIVED,
            self::DEPOSIT_FAILED,
            self::CHECKOUT_CREATED,
            self::CHECKOUT_COMPLETED,
            self::CHECKOUT_VOIDED,
            self::CHECKOUT_REOPENED,
            self::PAYMENT_CAPTURED,
            self::PAYMENT_RECORDED,
            self::PAYMENT_REFUNDED,
            self::REFUND_COMPLETED,
            self::PACKAGE_RESERVED,
            self::PACKAGE_REDEEMED,
            self::PACKAGE_RESTORED,
            self::WALLET_REDEEMED,
            self::WALLET_RESTORED,
            self::LOYALTY_REDEEMED,
            self::LOYALTY_RESTORED,
            self::MEMBERSHIP_DISCOUNT_APPLIED,
            self::STOCK_CONSUMED,
            self::STOCK_REVERSED,
            self::STOCK_ADJUSTED,
            self::STOCK_RESTOCKED,
        ];
    }
}
