<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Enums\AnalyticsScheduleFrequency;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use Carbon\Carbon;

/**
 * Finds due scheduled analytics saved reports and queues export jobs.
 */
class AnalyticsScheduledExportService
{
    public function __construct(
        private readonly AnalyticsExportService $exports,
    ) {}

    /**
     * @return array{dispatched: int, skipped: int}
     */
    public function runDue(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $dispatched = 0;
        $skipped = 0;

        $reports = AnalyticsSavedReport::query()
            ->where('is_scheduled', true)
            ->whereNull('archived_at')
            ->whereNotNull('schedule_frequency')
            ->get();

        foreach ($reports as $report) {
            if (! $this->isDue($report, $now)) {
                $skipped++;

                continue;
            }

            $this->exports->runSavedReport($report);
            $dispatched++;
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    public function isDue(AnalyticsSavedReport $report, Carbon $now): bool
    {
        $frequency = $report->schedule_frequency;
        if ($frequency === null || $frequency === '') {
            return false;
        }

        $time = $this->normalizeScheduleTime($report->schedule_time);
        $scheduledMoment = $now->copy()->setTimeFromTimeString($time);

        if ($now->lt($scheduledMoment)) {
            return false;
        }

        if ($frequency === AnalyticsScheduleFrequency::WEEKLY) {
            if ($report->schedule_day_of_week === null || (int) $report->schedule_day_of_week !== (int) $now->dayOfWeek) {
                return false;
            }
        }

        if ($frequency === AnalyticsScheduleFrequency::MONTHLY) {
            if ($report->schedule_day_of_month === null || (int) $report->schedule_day_of_month !== (int) $now->day) {
                return false;
            }
        }

        if ($report->last_run_at === null) {
            return true;
        }

        $last = $report->last_run_at->copy();

        return match ($frequency) {
            AnalyticsScheduleFrequency::DAILY => $last->lt($now->copy()->startOfDay()),
            AnalyticsScheduleFrequency::WEEKLY => $last->lt($now->copy()->startOfWeek()),
            AnalyticsScheduleFrequency::MONTHLY => $last->lt($now->copy()->startOfMonth()),
            default => false,
        };
    }

    private function normalizeScheduleTime(?string $time): string
    {
        if ($time === null || trim($time) === '') {
            return '00:00';
        }

        if (preg_match('/^\d{1,2}:\d{2}/', $time) === 1) {
            return strlen($time) === 4 ? '0'.$time : substr($time, 0, 5);
        }

        return '00:00';
    }
}
