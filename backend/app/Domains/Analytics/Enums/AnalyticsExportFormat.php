<?php

namespace App\Domains\Analytics\Enums;

final class AnalyticsExportFormat
{
    public const CSV = 'csv';

    public const JSON = 'json';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::CSV, self::JSON];
    }
}
