<?php

namespace App\Domains\Integrations\Enums;

final class ProviderSourceType
{
    public const NOTIFICATION_MESSAGE = 'notification_message';

    public const MARKETING_MESSAGE = 'marketing_message';

    public const PAYMENT_TRANSACTION = 'payment_transaction';

    public const PAYMENT_LINK = 'payment_link';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::NOTIFICATION_MESSAGE,
            self::MARKETING_MESSAGE,
            self::PAYMENT_TRANSACTION,
            self::PAYMENT_LINK,
        ];
    }
}
