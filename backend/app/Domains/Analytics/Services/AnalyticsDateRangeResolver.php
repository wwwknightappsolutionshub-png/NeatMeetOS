<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\DTOs\DateRange;
use Illuminate\Support\Carbon;

/**
 * Normalises the optional from/to query parameters into an inclusive
 * DateRange. When omitted it defaults to the most recent 30-day window.
 */
class AnalyticsDateRangeResolver
{
    private const DEFAULT_WINDOW_DAYS = 30;

    public function resolve(?string $from = null, ?string $to = null): DateRange
    {
        $toDate = ! empty($to)
            ? Carbon::parse($to)->endOfDay()
            : Carbon::now()->endOfDay();

        $fromDate = ! empty($from)
            ? Carbon::parse($from)->startOfDay()
            : $toDate->copy()->subDays(self::DEFAULT_WINDOW_DAYS - 1)->startOfDay();

        // Guard against an inverted range so downstream whereBetween stays valid.
        if ($fromDate->greaterThan($toDate)) {
            $swap = $fromDate->copy()->endOfDay();
            $fromDate = $toDate->copy()->startOfDay();
            $toDate = $swap;
        }

        return new DateRange($fromDate, $toDate);
    }
}
