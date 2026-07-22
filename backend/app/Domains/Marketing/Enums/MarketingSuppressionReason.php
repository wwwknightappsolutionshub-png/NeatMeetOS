<?php

namespace App\Domains\Marketing\Enums;

final class MarketingSuppressionReason
{
    public const UNSUBSCRIBE = 'unsubscribe';

    public const HARD_BOUNCE = 'hard_bounce';

    public const MANUAL = 'manual';

    public const COMPLAINT = 'complaint';

    public const INVALID_CONTACT = 'invalid_contact';

    public static function all(): array
    {
        return [
            self::UNSUBSCRIBE,
            self::HARD_BOUNCE,
            self::MANUAL,
            self::COMPLAINT,
            self::INVALID_CONTACT,
        ];
    }
}
