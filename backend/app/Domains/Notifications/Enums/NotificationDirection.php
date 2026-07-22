<?php

namespace App\Domains\Notifications\Enums;

final class NotificationDirection
{
    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    public static function all(): array
    {
        return [
            self::OUTBOUND,
            self::INBOUND,
        ];
    }
}
