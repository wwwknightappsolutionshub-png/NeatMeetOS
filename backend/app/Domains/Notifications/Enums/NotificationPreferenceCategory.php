<?php

namespace App\Domains\Notifications\Enums;

/**
 * Operational preference categories used to gate notification delivery.
 * These map onto the boolean category flags on notification_preferences.
 */
final class NotificationPreferenceCategory
{
    public const BOOKING = 'booking';

    public const PAYMENT = 'payment';

    public const MEMBERSHIP = 'membership';

    public const GENERAL = 'general';

    public static function all(): array
    {
        return [
            self::BOOKING,
            self::PAYMENT,
            self::MEMBERSHIP,
            self::GENERAL,
        ];
    }

    /**
     * Column on notification_preferences that stores the category flag.
     */
    public static function column(string $category): string
    {
        return match ($category) {
            self::BOOKING => 'booking_notifications',
            self::PAYMENT => 'payment_notifications',
            self::MEMBERSHIP => 'membership_notifications',
            default => 'general_notifications',
        };
    }
}
