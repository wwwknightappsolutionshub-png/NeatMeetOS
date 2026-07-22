<?php

namespace App\Domains\Integrations\Enums;

final class ProviderCategory
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const PAYMENT_GATEWAY = 'payment_gateway';

    public const GIFT_CARD = 'gift_card';

    public const GENERIC_WEBHOOK = 'generic_webhook';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::EMAIL,
            self::SMS,
            self::PAYMENT_GATEWAY,
            self::GIFT_CARD,
            self::GENERIC_WEBHOOK,
        ];
    }

    public static function fromNotificationChannel(string $channel): string
    {
        return match ($channel) {
            'sms', 'whatsapp' => self::SMS,
            default => self::EMAIL,
        };
    }

    public static function fromMarketingChannel(string $channel): string
    {
        return match ($channel) {
            'sms', 'whatsapp' => self::SMS,
            'push', 'in_app' => self::GENERIC_WEBHOOK,
            default => self::EMAIL,
        };
    }
}
