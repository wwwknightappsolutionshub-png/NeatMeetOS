<?php

namespace App\Domains\Integrations\Enums;

final class ProviderSourceDomain
{
    public const NOTIFICATIONS = 'notifications';

    public const MARKETING = 'marketing';

    public const PAYMENTS = 'payments';

    public const POS = 'pos';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::NOTIFICATIONS, self::MARKETING, self::PAYMENTS, self::POS];
    }
}
