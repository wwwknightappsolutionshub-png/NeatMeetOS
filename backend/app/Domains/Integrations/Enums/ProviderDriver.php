<?php

namespace App\Domains\Integrations\Enums;

final class ProviderDriver
{
    public const SIMULATION = 'simulation';

    public const MAILGUN = 'mailgun';

    public const TWILIO = 'twilio';

    public const STRIPE = 'stripe';

    public const MANUAL = 'manual';

    public const CUSTOM = 'custom';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::SIMULATION,
            self::MAILGUN,
            self::TWILIO,
            self::STRIPE,
            self::MANUAL,
            self::CUSTOM,
        ];
    }
}
