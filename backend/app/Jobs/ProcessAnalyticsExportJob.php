<?php

namespace App\Jobs;

use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use App\Domains\Analytics\Mail\AnalyticsScheduledExportMail;
use App\Domains\Analytics\Models\AnalyticsExportJob;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use App\Domains\Analytics\Services\AnalyticsExportService;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class ProcessAnalyticsExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $exportJobId,
    ) {}

    public function handle(
        TenantContext $tenantContext,
        AnalyticsExportService $exports,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenantContext->set($tenant);

        try {
            $job = AnalyticsExportJob::query()->find($this->exportJobId);
            if ($job === null || $job->status !== AnalyticsExportJobStatus::PENDING) {
                return;
            }

            $executed = $exports->execute($job);

            if ($executed->status !== AnalyticsExportJobStatus::COMPLETED) {
                return;
            }

            if ($executed->analytics_saved_report_id) {
                $report = AnalyticsSavedReport::query()->find($executed->analytics_saved_report_id);
                if ($report !== null) {
                    $emails = $this->normalizeEmails($report->delivery_emails ?? []);
                    if ($emails !== [] && $executed->file_path && $executed->file_disk) {
                        Mail::to($emails)->queue(new AnalyticsScheduledExportMail(
                            salonName: (string) ($tenant->name ?? 'NeatMeet'),
                            reportName: (string) $report->name,
                            exportJobId: (string) $executed->id,
                            fileDisk: (string) $executed->file_disk,
                            filePath: (string) $executed->file_path,
                            fileName: (string) ($executed->file_name ?: 'export.csv'),
                        ));
                    }
                }
            }
        } finally {
            $tenantContext->clear();
        }
    }

    /**
     * @param  mixed  $emails
     * @return list<string>
     */
    private function normalizeEmails(mixed $emails): array
    {
        if (! is_array($emails)) {
            return [];
        }

        $out = [];
        foreach ($emails as $email) {
            $value = strtolower(trim((string) $email));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }
}
