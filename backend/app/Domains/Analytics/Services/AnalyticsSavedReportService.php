<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Enums\AnalyticsExportFormat;
use App\Domains\Analytics\Enums\AnalyticsReportType;
use App\Domains\Analytics\Enums\AnalyticsScheduleFrequency;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalyticsSavedReportService
{
    public function __construct(
        private readonly AnalyticsScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AnalyticsSavedReport>
     */
    public function list(array $filters = []): Collection
    {
        $query = AnalyticsSavedReport::query()
            ->with('createdBy')
            ->orderByDesc('created_at');

        if (! empty($filters['report_type'])) {
            $query->where('report_type', $filters['report_type']);
        }

        if (array_key_exists('archived', $filters) && $filters['archived'] !== null) {
            $archived = filter_var($filters['archived'], FILTER_VALIDATE_BOOLEAN);
            $query->{$archived ? 'whereNotNull' : 'whereNull'}('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        return $query->get();
    }

    public function find(string $id): AnalyticsSavedReport
    {
        return $this->scope->findSavedReport($id)->load('createdBy');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $teamMemberId = null): AnalyticsSavedReport
    {
        $payload = $this->normalize($data);

        return DB::transaction(function () use ($payload, $teamMemberId) {
            $report = AnalyticsSavedReport::query()->create(array_merge($payload, [
                'tenant_id' => $this->scope->tenantId(),
                'created_by_team_member_id' => $teamMemberId,
            ]));

            $this->auditLogger->log('analytics_saved_report.created', $report, null, $report->only([
                'name', 'report_type', 'export_format', 'is_scheduled',
            ]));

            return $report->fresh('createdBy');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AnalyticsSavedReport $report, array $data): AnalyticsSavedReport
    {
        $payload = $this->normalize($data, partial: true);

        return DB::transaction(function () use ($report, $payload) {
            $old = $report->only(array_keys($payload));
            $report->fill($payload);
            $report->save();

            $this->auditLogger->log('analytics_saved_report.updated', $report, $old, $report->only(array_keys($payload)));

            return $report->fresh('createdBy');
        });
    }

    public function archive(AnalyticsSavedReport $report): AnalyticsSavedReport
    {
        return DB::transaction(function () use ($report) {
            if ($report->archived_at === null) {
                $report->archived_at = now();
                $report->save();
            }

            $this->auditLogger->log('analytics_saved_report.archived', $report, null, [
                'archived_at' => $report->archived_at?->toIso8601String(),
            ]);

            return $report->fresh('createdBy');
        });
    }

    /**
     * Normalize + validate the incoming payload. On create all core fields are
     * required; on update only supplied keys are validated/applied.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        $out = [];

        if (! $partial || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['name' => ['A report name is required.']]);
            }
            $out['name'] = $name;
        }

        if (! $partial || array_key_exists('report_type', $data)) {
            $type = $data['report_type'] ?? null;
            if (! in_array($type, AnalyticsReportType::all(), true)) {
                throw ValidationException::withMessages(['report_type' => ['Invalid analytics report type.']]);
            }
            $out['report_type'] = $type;
        }

        if (! $partial || array_key_exists('export_format', $data)) {
            $format = $data['export_format'] ?? AnalyticsExportFormat::CSV;
            if (! in_array($format, AnalyticsExportFormat::all(), true)) {
                throw ValidationException::withMessages(['export_format' => ['Invalid export format.']]);
            }
            $out['export_format'] = $format;
        }

        if (array_key_exists('filters', $data) || array_key_exists('filters_json', $data)) {
            $out['filters_json'] = $this->normalizeFilters($data['filters'] ?? $data['filters_json'] ?? null);
        } elseif (! $partial) {
            $out['filters_json'] = null;
        }

        $this->applyScheduleFields($data, $out, $partial);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $out
     */
    private function applyScheduleFields(array $data, array &$out, bool $partial): void
    {
        if (! $partial || array_key_exists('is_scheduled', $data)) {
            $out['is_scheduled'] = (bool) ($data['is_scheduled'] ?? false);
        }

        if (array_key_exists('schedule_frequency', $data)) {
            $freq = $data['schedule_frequency'];
            if ($freq !== null && ! in_array($freq, AnalyticsScheduleFrequency::all(), true)) {
                throw ValidationException::withMessages(['schedule_frequency' => ['Invalid schedule frequency.']]);
            }
            $out['schedule_frequency'] = $freq;
        } elseif (! $partial) {
            $out['schedule_frequency'] = null;
        }

        foreach (['schedule_day_of_week', 'schedule_day_of_month'] as $field) {
            if (array_key_exists($field, $data)) {
                $out[$field] = $data[$field] !== null ? (int) $data[$field] : null;
            } elseif (! $partial) {
                $out[$field] = null;
            }
        }

        if (array_key_exists('schedule_time', $data)) {
            $out['schedule_time'] = $data['schedule_time'] !== null ? (string) $data['schedule_time'] : null;
        } elseif (! $partial) {
            $out['schedule_time'] = null;
        }

        if (array_key_exists('delivery_emails', $data)) {
            $out['delivery_emails'] = $this->normalizeDeliveryEmails($data['delivery_emails']);
        } elseif (! $partial) {
            $out['delivery_emails'] = null;
        }
    }

    /**
     * @return list<string>|null
     */
    private function normalizeDeliveryEmails(mixed $emails): ?array
    {
        if ($emails === null || $emails === '') {
            return null;
        }

        if (is_string($emails)) {
            $emails = preg_split('/[\s,;]+/', $emails) ?: [];
        }

        if (! is_array($emails)) {
            throw ValidationException::withMessages([
                'delivery_emails' => ['Delivery emails must be a list of addresses.'],
            ]);
        }

        $clean = [];
        foreach ($emails as $email) {
            $value = strtolower(trim((string) $email));
            if ($value === '') {
                continue;
            }
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'delivery_emails' => ["Invalid delivery email: {$value}"],
                ]);
            }
            $clean[] = $value;
        }

        $clean = array_values(array_unique($clean));
        if (count($clean) > 10) {
            throw ValidationException::withMessages([
                'delivery_emails' => ['At most 10 delivery emails are allowed.'],
            ]);
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Retain only recognised analytics filter keys so saved presets stay stable.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeFilters(mixed $filters): ?array
    {
        if (! is_array($filters)) {
            return null;
        }

        $allowed = ['from', 'to', 'location_id', 'provider_id'];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                $clean[$key] = (string) $filters[$key];
            }
        }

        return $clean === [] ? null : $clean;
    }
}
