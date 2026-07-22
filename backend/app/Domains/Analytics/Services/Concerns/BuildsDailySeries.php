<?php

namespace App\Domains\Analytics\Services\Concerns;

use App\Domains\Analytics\DTOs\DateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Helpers for producing zero-filled, database-agnostic daily series.
 *
 * Date bucketing is performed in PHP (rather than via SQL DATE()/date_trunc)
 * so the same code path works identically on SQLite (tests) and PostgreSQL
 * (production).
 */
trait BuildsDailySeries
{
    /**
     * @return array<int, string> ordered list of Y-m-d keys covering the range
     */
    protected function dayKeys(DateRange $range): array
    {
        $keys = [];
        $cursor = $range->from->copy()->startOfDay();
        $end = $range->to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Group a collection of rows into a zero-filled daily series.
     *
     * @param  Collection<int, object>  $rows
     * @param  string  $dateField  timestamp attribute on each row
     * @param  callable(object): array<string, int|float>  $extract  metrics per row
     * @param  array<string, int|float>  $template  default metric values per day
     * @return array<int, array<string, mixed>>
     */
    protected function dailySeries(
        DateRange $range,
        Collection $rows,
        string $dateField,
        callable $extract,
        array $template,
    ): array {
        $buckets = [];
        foreach ($this->dayKeys($range) as $day) {
            $buckets[$day] = $template;
        }

        foreach ($rows as $row) {
            $value = $row->{$dateField} ?? null;
            if ($value === null) {
                continue;
            }
            $day = Carbon::parse($value)->format('Y-m-d');
            if (! isset($buckets[$day])) {
                continue;
            }
            foreach ($extract($row) as $metric => $amount) {
                $buckets[$day][$metric] = ($buckets[$day][$metric] ?? 0) + $amount;
            }
        }

        $series = [];
        foreach ($buckets as $day => $metrics) {
            $series[] = array_merge(['date' => $day], $metrics);
        }

        return $series;
    }
}
