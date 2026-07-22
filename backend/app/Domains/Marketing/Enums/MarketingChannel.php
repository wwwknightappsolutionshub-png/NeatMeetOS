<?php

namespace App\Domains\Marketing\Enums;

final class MarketingChannel
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    /** Web Push to member PWA subscriptions (CRM MemberPushDispatchService). */
    public const PUSH = 'push';

    /** Member portal in-app inbox (CRM ClientNotice). */
    public const IN_APP = 'in_app';

    public static function all(): array
    {
        return [
            self::EMAIL,
            self::SMS,
            self::WHATSAPP,
            self::PUSH,
            self::IN_APP,
        ];
    }

    public static function isNative(string $channel): bool
    {
        return in_array($channel, [self::PUSH, self::IN_APP], true);
    }
}
