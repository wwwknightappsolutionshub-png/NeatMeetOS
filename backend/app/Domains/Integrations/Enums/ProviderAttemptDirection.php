<?php

namespace App\Domains\Integrations\Enums;

final class ProviderAttemptDirection
{
    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::OUTBOUND, self::INBOUND];
    }
}
