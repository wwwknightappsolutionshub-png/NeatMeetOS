<?php

namespace App\Domains\Notifications\Enums;

final class NotificationCategory
{
    public const BOOKING = 'booking';

    public const PAYMENTS = 'payments';

    public const MEMBERSHIP = 'membership';

    public const CRM = 'crm';

    public const GENERAL = 'general';

    public static function all(): array
    {
        return [
            self::BOOKING,
            self::PAYMENTS,
            self::MEMBERSHIP,
            self::CRM,
            self::GENERAL,
        ];
    }
}
