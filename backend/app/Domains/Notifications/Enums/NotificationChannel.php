<?php

namespace App\Domains\Notifications\Enums;

final class NotificationChannel
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    public const PUSH = 'push';

    /** Member PWA inbox via CRM ClientNotice. */
    public const IN_APP = 'in_app';

    public const INTERNAL_NOTE = 'internal_note';

    public static function all(): array
    {
        return [
            self::EMAIL,
            self::SMS,
            self::WHATSAPP,
            self::PUSH,
            self::IN_APP,
            self::INTERNAL_NOTE,
        ];
    }

    public static function isNative(string $channel): bool
    {
        return $channel === self::IN_APP;
    }
}
