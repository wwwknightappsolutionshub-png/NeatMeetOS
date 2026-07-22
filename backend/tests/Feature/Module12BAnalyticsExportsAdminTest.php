<?php

namespace Tests\Feature;

use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use App\Domains\Analytics\Models\AnalyticsExportJob;
use App\Domains\Analytics\Models\AnalyticsSavedReport;
use App\Domains\Analytics\Services\AnalyticsExportTransformer;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class Module12BAnalyticsExportsAdminTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    protected function modulePermissions(): array
    {
        return [
            'analytics.view',
            'analytics.reporting.view',
            'analytics.exports.manage',
            'crm.view',
            'booking.view',
        ];
    }

    public function test_saved_report_crud(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $created = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports', [
                'name' => 'Weekly bookings',
                'report_type' => 'bookings',
                'export_format' => 'csv',
                'filters' => ['location_id' => $ctx['location']->id],
                'is_scheduled' => true,
                'schedule_frequency' => 'weekly',
                'schedule_day_of_week' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.report_type', 'bookings')
            ->assertJsonPath('data.is_scheduled', true)
            ->assertJsonPath('data.schedule_frequency', 'weekly')
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports/'.$created)
            ->assertOk()
            ->assertJsonPath('data.name', 'Weekly bookings');

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/analytics/saved-reports/'.$created, [
                'name' => 'Weekly bookings v2',
                'export_format' => 'json',
                'filters' => ['from' => '2026-06-01', 'to' => '2026-07-01'],
                'is_scheduled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Weekly bookings v2')
            ->assertJsonPath('data.export_format', 'json')
            ->assertJsonPath('data.filters.from', '2026-06-01')
            ->assertJsonPath('data.filters.to', '2026-07-01')
            ->assertJsonPath('data.is_scheduled', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_saved_report.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_saved_report.updated']);
    }

    public function test_saved_report_archive(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $report = $this->createSavedReport($ctx, 'overview');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/analytics/saved-reports/'.$report->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.archived_at', fn ($v) => $v !== null);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_saved_report.archived']);
    }

    public function test_ad_hoc_csv_export_completes_and_persists_file(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedBookings($ctx);

        $response = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'bookings',
                'export_format' => 'csv',
                'filters' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AnalyticsExportJobStatus::COMPLETED)
            ->assertJsonPath('data.file_name', fn ($v) => is_string($v) && str_ends_with($v, '.csv'))
            ->assertJsonPath('data.download_url', fn ($v) => $v !== null);

        $job = AnalyticsExportJob::query()->firstOrFail();
        $this->assertNotNull($job->file_path);
        Storage::disk('local')->assertExists($job->file_path);
        $this->assertNotNull($job->row_count);

        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_export.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_export.completed']);
    }

    public function test_ad_hoc_json_export_completes_and_persists_file(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'json',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AnalyticsExportJobStatus::COMPLETED)
            ->assertJsonPath('data.file_name', fn ($v) => is_string($v) && str_ends_with($v, '.json'));

        $job = AnalyticsExportJob::query()->firstOrFail();
        Storage::disk('local')->assertExists($job->file_path);
        $content = Storage::disk('local')->get($job->file_path);
        $decoded = json_decode($content, true);
        $this->assertSame('overview', $decoded['report_type']);
        $this->assertArrayHasKey('generated_at', $decoded);
        $this->assertArrayHasKey('filters', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('range', $decoded);
    }

    public function test_export_from_saved_report(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $report = $this->createSavedReport($ctx, 'revenue', 'json');

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports/'.$report->id.'/run')
            ->assertCreated()
            ->assertJsonPath('data.status', AnalyticsExportJobStatus::COMPLETED)
            ->assertJsonPath('data.report_type', 'revenue')
            ->assertJsonPath('data.saved_report.id', $report->id);

        $this->assertNotNull($report->fresh()->last_run_at);

        $job = AnalyticsExportJob::query()->firstOrFail();
        $this->assertSame($report->id, $job->analytics_saved_report_id);
    }

    public function test_download_returns_file_for_completed_export(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $id = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->get('/api/v1/admin/analytics/exports/'.$id.'/download')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_export_jobs_list_and_show(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $id = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'clients',
                'export_format' => 'csv',
            ])
            ->json('data.id');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/exports?report_type=clients')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/exports/'.$id)
            ->assertOk()
            ->assertJsonPath('data.report_type', 'clients');
    }

    public function test_invalid_report_type_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'not-a-real-type',
                'export_format' => 'csv',
            ])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports', [
                'name' => 'Bad',
                'report_type' => 'bogus',
            ])
            ->assertStatus(422);
    }

    public function test_archived_saved_report_cannot_be_run(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $report = $this->createSavedReport($ctx, 'overview');

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/analytics/saved-reports/'.$report->id.'/archive')
            ->assertOk();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports/'.$report->id.'/run')
            ->assertStatus(422);
    }

    public function test_tenant_isolation_for_saved_reports_and_exports(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignReport = AnalyticsSavedReport::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'report_type' => 'overview',
            'export_format' => 'csv',
        ]);

        $foreignJob = AnalyticsExportJob::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'report_type' => 'overview',
            'export_format' => 'csv',
            'status' => AnalyticsExportJobStatus::COMPLETED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports/'.$foreignReport->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/exports/'.$foreignJob->id)
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_permission_gate_blocks_without_exports_manage(): void
    {
        $ctx = $this->seedTenantContext(['analytics.view', 'analytics.reporting.view']);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/saved-reports')
            ->assertForbidden();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertForbidden();
    }

    public function test_invalid_export_format_rejected(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'pdf',
            ])
            ->assertStatus(422);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports', [
                'name' => 'Bad format',
                'report_type' => 'overview',
                'export_format' => 'xlsx',
            ])
            ->assertStatus(422);
    }

    public function test_csv_export_contains_parseable_headers_and_rows(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedBookings($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'bookings',
                'export_format' => 'csv',
            ])
            ->assertCreated();

        $job = AnalyticsExportJob::query()->firstOrFail();
        $content = Storage::disk('local')->get($job->file_path);
        $lines = array_values(array_filter(explode("\n", trim($content))));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV should include header + at least one data row');

        $headers = str_getcsv($lines[0]);
        $this->assertContains('date', $headers);
        $this->assertContains('total', $headers);
        $this->assertContains('completed', $headers);

        $dataRow = str_getcsv($lines[1]);
        $this->assertCount(count($headers), $dataRow);
    }

    public function test_overview_csv_export_is_not_blank_when_data_exists(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());
        $this->seedBookings($ctx);

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertCreated()
            ->assertJsonPath('data.row_count', 1);

        $job = AnalyticsExportJob::query()->firstOrFail();
        $content = Storage::disk('local')->get($job->file_path);
        $this->assertStringContainsString('total_appointments', $content);
        $this->assertStringContainsString('2', $content);
    }

    public function test_failed_export_records_status_and_reason_without_500(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->mock(AnalyticsExportTransformer::class, function ($mock) {
            $mock->shouldReceive('toCsv')
                ->once()
                ->andThrow(new \RuntimeException('Simulated CSV failure'));
        });

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AnalyticsExportJobStatus::FAILED)
            ->assertJsonPath('data.failure_reason', 'Simulated CSV failure')
            ->assertJsonPath('data.download_url', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics_export.failed']);
    }

    public function test_download_blocked_for_non_completed_export(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $job = AnalyticsExportJob::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'report_type' => 'overview',
            'export_format' => 'csv',
            'status' => AnalyticsExportJobStatus::FAILED,
            'failure_reason' => 'Prior failure',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->get('/api/v1/admin/analytics/exports/'.$job->id.'/download')
            ->assertNotFound();
    }

    public function test_download_returns_404_when_file_missing_on_disk(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $id = $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertCreated()
            ->json('data.id');

        $job = AnalyticsExportJob::query()->findOrFail($id);
        Storage::disk('local')->delete($job->file_path);

        $this->withTenantAuth($ctx['token'])
            ->get('/api/v1/admin/analytics/exports/'.$id.'/download')
            ->assertNotFound();
    }

    public function test_cross_tenant_export_download_is_blocked(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $path = 'analytics/exports/'.$ctx['otherTenant']->id.'/foreign.csv';
        Storage::disk('local')->put($path, 'tenant,secret');

        $foreignJob = AnalyticsExportJob::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'report_type' => 'overview',
            'export_format' => 'csv',
            'status' => AnalyticsExportJobStatus::COMPLETED,
            'file_disk' => 'local',
            'file_path' => $path,
            'file_name' => 'foreign.csv',
            'completed_at' => now(),
        ]);

        $this->withTenantAuth($ctx['token'])
            ->get('/api/v1/admin/analytics/exports/'.$foreignJob->id.'/download')
            ->assertNotFound();
    }

    public function test_cross_tenant_saved_report_mutations_are_blocked(): void
    {
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $foreignReport = AnalyticsSavedReport::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'name' => 'Foreign',
            'report_type' => 'overview',
            'export_format' => 'csv',
        ]);

        $this->withTenantAuth($ctx['token'])
            ->putJson('/api/v1/admin/analytics/saved-reports/'.$foreignReport->id, ['name' => 'Hijack'])
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->patchJson('/api/v1/admin/analytics/saved-reports/'.$foreignReport->id.'/archive')
            ->assertNotFound();

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/saved-reports/'.$foreignReport->id.'/run')
            ->assertNotFound();
    }

    public function test_export_list_excludes_other_tenant_jobs(): void
    {
        Storage::fake('local');
        $ctx = $this->seedTenantContext($this->modulePermissions());

        $this->withTenantAuth($ctx['token'])
            ->postJson('/api/v1/admin/analytics/exports', [
                'report_type' => 'overview',
                'export_format' => 'csv',
            ])
            ->assertCreated();

        AnalyticsExportJob::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'report_type' => 'bookings',
            'export_format' => 'csv',
            'status' => AnalyticsExportJobStatus::COMPLETED,
        ]);

        $this->withTenantAuth($ctx['token'])
            ->getJson('/api/v1/admin/analytics/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.report_type', 'overview');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function createSavedReport(array $ctx, string $type, string $format = 'csv'): AnalyticsSavedReport
    {
        return AnalyticsSavedReport::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'name' => ucfirst($type).' preset',
            'report_type' => $type,
            'export_format' => $format,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function seedBookings(array $ctx): void
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $ctx['tenant']->id,
            'first_name' => 'Export',
            'last_name' => 'Client',
            'email' => 'export.'.Str::random(6).'@example.com',
            'primary_location_id' => $ctx['location']->id,
            'is_active' => true,
        ]);

        foreach ([Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED] as $index => $status) {
            Appointment::withoutGlobalScopes()->create([
                'tenant_id' => $ctx['tenant']->id,
                'location_id' => $ctx['location']->id,
                'client_id' => $client->id,
                'team_member_id' => $ctx['teamMember']->id,
                'starts_at' => now()->subDays(3 - $index),
                'ends_at' => now()->subDays(3 - $index)->addHour(),
                'status' => $status,
            ]);
        }
    }
}
