<?php

namespace App\Domains\Notifications\Enums;

final class NotificationAttemptProvider
{
    public const SIMULATION = 'simulation';

    public const MANUAL = 'manual';

    public const EMAIL_LINK = 'email_link';

    public const SMS_LINK = 'sms_link';

    public const WHATSAPP_LINK = 'whatsapp_link';

    public const GENIUS = 'genius';

    public const IN_APP = 'in_app';

    public static function all(): array
    {
        return [
            self::SIMULATION,
            self::MANUAL,
            self::EMAIL_LINK,
            self::SMS_LINK,
            self::WHATSAPP_LINK,
            self::GENIUS,
            self::IN_APP,
        ];
    }
}
