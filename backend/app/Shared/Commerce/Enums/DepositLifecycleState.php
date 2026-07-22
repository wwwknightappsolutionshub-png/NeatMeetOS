<?php

namespace App\Shared\Commerce\Enums;

final class DepositLifecycleState
{
    public const NOT_REQUIRED = 'not_required';

    public const REQUIRED = 'required';

    public const WAIVED = 'waived';

    public const COLLECTION_PENDING = 'collection_pending';

    public const COLLECTED = 'collected';

    public const APPLIED_TO_CHECKOUT = 'applied_to_checkout';

    public const REFUNDED = 'refunded';

    public const FORFEITED = 'forfeited';

    public static function all(): array
    {
        return [
            self::NOT_REQUIRED,
            self::REQUIRED,
            self::WAIVED,
            self::COLLECTION_PENDING,
            self::COLLECTED,
            self::APPLIED_TO_CHECKOUT,
            self::REFUNDED,
            self::FORFEITED,
        ];
    }
}
