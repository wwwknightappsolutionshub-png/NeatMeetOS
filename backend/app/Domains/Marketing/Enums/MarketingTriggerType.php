<?php

namespace App\Domains\Marketing\Enums;

final class MarketingTriggerType
{
    public const BOOKING_REMINDER = 'booking_reminder';

    public const REBOOKING_NUDGE = 'rebooking_nudge';

    public const WIN_BACK = 'win_back';

    public const REVIEW_REQUEST = 'review_request';

    public const BIRTHDAY = 'birthday';

    public const MEMBERSHIP_RENEWAL = 'membership_renewal';

    public static function all(): array
    {
        return [
            self::BOOKING_REMINDER,
            self::REBOOKING_NUDGE,
            self::WIN_BACK,
            self::REVIEW_REQUEST,
            self::BIRTHDAY,
            self::MEMBERSHIP_RENEWAL,
        ];
    }

    public static function active(): array
    {
        return [
            self::BOOKING_REMINDER,
            self::REBOOKING_NUDGE,
            self::WIN_BACK,
            self::REVIEW_REQUEST,
        ];
    }
}
