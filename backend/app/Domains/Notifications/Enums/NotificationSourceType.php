<?php

namespace App\Domains\Notifications\Enums;

final class NotificationSourceType
{
    public const BOOKING = 'booking';

    public const PAYMENTS = 'payments';

    public const MEMBERSHIPS = 'memberships';

    public const MARKETING = 'marketing';

    public const CRM = 'crm';

    public const MANUAL = 'manual';

    public const SYSTEM = 'system';

    public static function all(): array
    {
        return [
            self::BOOKING,
            self::PAYMENTS,
            self::MEMBERSHIPS,
            self::MARKETING,
            self::CRM,
            self::MANUAL,
            self::SYSTEM,
        ];
    }
}
