<?php

namespace App\Domains\Integrations\Enums;

final class ProviderAttemptStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const SUPPRESSED = 'suppressed';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::SENT,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::SUPPRESSED,
        ];
    }
}
