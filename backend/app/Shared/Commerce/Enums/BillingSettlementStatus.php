<?php

namespace App\Shared\Commerce\Enums;

final class BillingSettlementStatus
{
    public const NOT_APPLICABLE = 'not_applicable';

    public const UNSETTLED = 'unsettled';

    public const PARTIALLY_SETTLED = 'partially_settled';

    public const SETTLED = 'settled';

    public static function all(): array
    {
        return [
            self::NOT_APPLICABLE,
            self::UNSETTLED,
            self::PARTIALLY_SETTLED,
            self::SETTLED,
        ];
    }
}
