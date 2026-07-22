<?php

namespace App\Domains\Marketing\Enums;

final class MarketingMessageStatus
{
    public const PENDING = 'pending';

    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const SKIPPED = 'skipped';

    public const SUPPRESSED = 'suppressed';

    public const UNSUBSCRIBED = 'unsubscribed';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::QUEUED,
            self::PROCESSING,
            self::SENT,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::SKIPPED,
            self::SUPPRESSED,
            self::UNSUBSCRIBED,
        ];
    }
}
