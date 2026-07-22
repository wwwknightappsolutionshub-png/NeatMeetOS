<?php

namespace App\Domains\Pos\Enums;

final class ReceiptDeliveryStatus
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public static function all(): array
    {
        return [self::PENDING, self::SENT, self::FAILED];
    }
}
