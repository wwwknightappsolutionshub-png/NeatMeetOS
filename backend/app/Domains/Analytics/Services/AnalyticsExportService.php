<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Enums\AnalyticsExportFormat;
use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use App\Domains\Analytics\Enums\AnalyticsReportType;
use App\Domains\Analytics\Models\AnalyticsExportJob;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use App\Jobs\ProcessAnalyticsExportJob;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Creates analytics export jobs and queues execution.
 *
 * Jobs are created as pending and processed by ProcessAnalyticsExportJob
 * (sync queue in tests; database/redis workers in production).
 */
class AnalyticsExportService
{
    private const DISK = 'local';

    public function __construct(
        private readonly AnalyticsScopeValidator $scope,
        private readonly AnalyticsDateRangeResolver $dateRangeResolver,
        private readonly AnalyticsExportTransformer $transformer,
        private readonly AuditLogger $auditLogger,
        private readonly AnalyticsOverviewService $overviewService,
        private readonly BookingAnalyticsService $bookingAnalytics,
        private readonly RevenueAnalyticsService $revenueAnalytics,
        private readonly ClientAnalyticsService $clientAnalytics,
        private readonly InventoryAnalyticsService $inventoryAnalytics,
        private readonly CommunicationsAnalyticsService $communicationsAnalytics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AnalyticsExportJob>
     */
    public function list(array $filters = []): Collection
    {
        $query = AnalyticsExportJob::query()
            ->with('savedReport')
            ->orderByDesc('created_at');

        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function find(string $id): AnalyticsExportJob
    {
        return $this->scope->findExportJob($id)->load('savedReport');
    }

    /**
     * Create + queue an ad-hoc export job.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAdHoc(array $data, ?string $teamMemberId = null): AnalyticsExportJob
    {
        $reportType = $this->assertReportType($data['report_type'] ?? null);
        $format = $this->assertFormat($data['export_format'] ?? AnalyticsExportFormat::CSV);
        $filters = $this->normalizeFilters($data['filters'] ?? null);

        $job = $this->createJob($reportType, $format, $filters, $teamMemberId, null);
        ProcessAnalyticsExportJob::dispatch($this->scope->tenantId(), $job->id);

        return $job->fresh('savedReport');
    }

    /**
     * Create + queue an export job from a saved report preset.
     */
    public function runSavedReport(AnalyticsSavedReport $report, ?string $teamMemberId = null): AnalyticsExportJob
    {
        if ($report->isArchived()) {
            throw ValidationException::withMessages([
                'saved_report' => ['Archived saved reports cannot be run.'],
            ]);
        }

        $job = $this->createJob(
            $report->report_type,
            $report->export_format,
            $this->normalizeFilters($report->filters_json ?? []),
            $teamMemberId,
            $report->id,
        );

        // Mark run time when queued so the scheduler does not double-dispatch.
        $report->last_run_at = now();
        $report->save();

        ProcessAnalyticsExportJob::dispatch($this->scope->tenantId(), $job->id);

        return $job->fresh('savedReport');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function createJob(string $reportType, string $format, array $filters, ?string $teamMemberId, ?string $savedReportId): AnalyticsExportJob
    {
        return DB::transaction(function () use ($reportType, $format, $filters, $teamMemberId, $savedReportId) {
            $job = AnalyticsExportJob::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'analytics_saved_report_id' => $savedReportId,
                'created_by_team_member_id' => $teamMemberId,
                'report_type' => $reportType,
                'export_format' => $format,
                'status' => AnalyticsExportJobStatus::PENDING,
                'filters_json' => $filters === [] ? null : $filters,
            ]);

            $this->auditLogger->log('analytics_export.created', $job, null, $job->only([
                'report_type', 'export_format', 'analytics_saved_report_id',
            ]));

            return $job;
        });
    }

    /**
     * Execute the export synchronously, persisting the generated file + status.
     */
    public function execute(AnalyticsExportJob $job): AnalyticsExportJob
    {
        $job->status = AnalyticsExportJobStatus::PROCESSING;
        $job->started_at = now();
        $job->save();

        try {
            $payload = $this->buildPayload($job);
            [$filename, $path, $rowCount] = $this->writeFile($job, $payload);

            $job->status = AnalyticsExportJobStatus::COMPLETED;
            $job->file_disk = self::DISK;
            $job->file_name = $filename;
            $job->file_path = $path;
            $job->row_count = $rowCount;
            $job->completed_at = now();
            $job->save();

            $this->auditLogger->log('analytics_export.completed', $job, null, [
                'file_name' => $filename,
                'row_count' => $rowCount,
            ]);
        } catch (Throwable $e) {
            $job->status = AnalyticsExportJobStatus::FAILED;
            $job->failed_at = now();
            $job->failure_reason = $e->getMessage();
            $job->save();

            $this->auditLogger->log('analytics_export.failed', $job, null, [
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return $job->fresh('savedReport');
    }

    /**
     * Run the matching 12A analytics service for the job's report type.
     *
     * @return array{report: array<string, mixed>, from: string|null, to: string|null, location_id: string|null, provider_id: string|null}
     */
    private function buildPayload(AnalyticsExportJob $job): array
    {
        $filters = $job->filters_json ?? [];
        $range = $this->dateRangeResolver->resolve($filters['from'] ?? null, $filters['to'] ?? null);
        $locationId = $filters['location_id'] ?? null;
        $providerId = $filters['provider_id'] ?? null;
        $tenantId = $this->scope->tenantId();

        $report = match ($job->report_type) {
            AnalyticsReportType::OVERVIEW => $this->overviewService->report($tenantId, $range, $locationId, $providerId),
            AnalyticsReportType::BOOKINGS => $this->bookingAnalytics->report($tenantId, $range, $locationId, $providerId),
            AnalyticsReportType::REVENUE => $this->revenueAnalytics->report($tenantId, $range, $locationId, $providerId),
            AnalyticsReportType::CLIENTS => $this->clientAnalytics->report($tenantId, $range, $locationId),
            AnalyticsReportType::INVENTORY => $this->inventoryAnalytics->report($tenantId, $range, $locationId),
            AnalyticsReportType::COMMUNICATIONS => $this->communicationsAnalytics->report($tenantId, $range),
            default => throw ValidationException::withMessages(['report_type' => ['Unsupported analytics report type.']]),
        };

        return [
            'report' => $report,
            'from' => $range->from->toIso8601String(),
            'to' => $range->to->toIso8601String(),
            'location_id' => $locationId,
            'provider_id' => $providerId,
        ];
    }

    /**
     * @param  array{report: array<string, mixed>, from: string|null, to: string|null, location_id: string|null, provider_id: string|null}  $payload
     * @return array{0: string, 1: string, 2: int}  [filename, path, rowCount]
     */
    private function writeFile(AnalyticsExportJob $job, array $payload): array
    {
        $timestamp = now()->format('Y-m-d-His');
        $filename = "analytics-{$job->report_type}-{$timestamp}.{$job->export_format}";
        $path = "analytics/exports/{$job->tenant_id}/{$filename}";

        if ($job->export_format === AnalyticsExportFormat::JSON) {
            $content = json_encode([
                'report_type' => $job->report_type,
                'generated_at' => now()->toIso8601String(),
                'filters' => $job->filters_json ?? (object) [],
                'range' => [
                    'from' => $payload['from'],
                    'to' => $payload['to'],
                ],
                'data' => $payload['report'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            Storage::disk(self::DISK)->put($path, $content);

            return [$filename, $path, $this->rowCountFor($job->report_type, $payload['report'])];
        }

        $csv = $this->transformer->toCsv($job->report_type, $payload['report']);
        $content = $this->renderCsv($csv['headers'], $csv['rows']);
        Storage::disk(self::DISK)->put($path, $content);

        return [$filename, $path, count($csv['rows'])];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function renderCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($headers !== []) {
            fputcsv($handle, $headers);
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Best-effort logical row count for JSON exports (primary series length).
     *
     * @param  array<string, mixed>  $report
     */
    private function rowCountFor(string $reportType, array $report): int
    {
        return match ($reportType) {
            AnalyticsReportType::BOOKINGS, AnalyticsReportType::REVENUE => count($report['daily'] ?? []),
            AnalyticsReportType::CLIENTS => count($report['growth'] ?? []),
            AnalyticsReportType::INVENTORY => count($report['low_stock'] ?? []),
            AnalyticsReportType::COMMUNICATIONS => count($report['marketing']['by_channel'] ?? []) + count($report['notifications']['by_channel'] ?? []),
            default => 1,
        };
    }

    private function assertReportType(?string $type): string
    {
        if (! in_array($type, AnalyticsReportType::all(), true)) {
            throw ValidationException::withMessages(['report_type' => ['Invalid analytics report type.']]);
        }

        return $type;
    }

    private function assertFormat(?string $format): string
    {
        if (! in_array($format, AnalyticsExportFormat::all(), true)) {
            throw ValidationException::withMessages(['export_format' => ['Invalid export format.']]);
        }

        return $format;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $allowed = ['from', 'to', 'location_id', 'provider_id'];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                $clean[$key] = (string) $filters[$key];
            }
        }

        return $clean;
    }
}
