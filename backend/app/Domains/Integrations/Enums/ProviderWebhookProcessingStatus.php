<?php

namespace App\Domains\Integrations\Enums;

final class ProviderWebhookProcessingStatus
{
    public const RECEIVED = 'received';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const FAILED = 'failed';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::RECEIVED, self::PROCESSED, self::IGNORED, self::FAILED];
    }
}
