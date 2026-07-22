<?php

namespace App\Domains\Marketing\Enums;

final class MarketingMessagePurpose
{
    public const BOOKING_REMINDER = 'booking_reminder';

    public const REBOOKING_NUDGE = 'rebooking_nudge';

    public const WIN_BACK = 'win_back';

    public const REVIEW_REQUEST = 'review_request';

    public const BROADCAST = 'broadcast';

    public const MEMBERSHIP_REMINDER = 'membership_reminder';

    public const WORKFLOW = 'workflow';

    public const WELCOME = 'welcome';

    public const BIRTHDAY = 'birthday';

    public const MONTHLY_BOOK_NUDGE = 'monthly_book_nudge';

    public static function all(): array
    {
        return [
            self::BOOKING_REMINDER,
            self::REBOOKING_NUDGE,
            self::WIN_BACK,
            self::REVIEW_REQUEST,
            self::BROADCAST,
            self::MEMBERSHIP_REMINDER,
            self::WORKFLOW,
            self::WELCOME,
            self::BIRTHDAY,
            self::MONTHLY_BOOK_NUDGE,
        ];
    }
}
