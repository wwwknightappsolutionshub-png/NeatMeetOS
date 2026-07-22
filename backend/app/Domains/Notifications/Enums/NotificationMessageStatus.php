<?php

namespace App\Domains\Notifications\Enums;

final class NotificationMessageStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const SUPPRESSED = 'suppressed';

    public static function all(): array
    {
        return [
            self::QUEUED,
            self::PROCESSING,
            self::SENT,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::SUPPRESSED,
        ];
    }

    /**
     * Statuses considered a completed successful outbound delivery.
     */
    public static function successful(): array
    {
        return [self::SENT, self::DELIVERED];
    }

    /**
     * Terminal statuses that cannot transition further.
     */
    public static function terminal(): array
    {
        return [self::DELIVERED, self::FAILED, self::CANCELLED, self::SUPPRESSED];
    }
}
