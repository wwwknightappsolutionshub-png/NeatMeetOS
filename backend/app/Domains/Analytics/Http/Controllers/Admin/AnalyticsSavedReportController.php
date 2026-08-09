<?php

namespace App\Domains\Analytics\Http\Controllers\Admin;

use App\Domains\Analytics\Enums\AnalyticsExportFormat;
use App\Domains\Analytics\Enums\AnalyticsReportType;
use App\Domains\Analytics\Enums\AnalyticsScheduleFrequency;
use App\Domains\Analytics\Http\Resources\AnalyticsExportJobResource;
use App\Domains\Analytics\Http\Resources\AnalyticsSavedReportResource;
use App\Domains\Analytics\Services\AnalyticsExportService;
use App\Domains\Analytics\Services\AnalyticsSavedReportService;
use App\Domains\Analytics\Services\AnalyticsScopeValidator;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsSavedReportController extends Controller
{
    public function __construct(
        private readonly AnalyticsSavedReportService $savedReports,
        private readonly AnalyticsExportService $exports,
        private readonly AnalyticsScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'report_type' => ['nullable', Rule::in(AnalyticsReportType::all())],
            'archived' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            AnalyticsSavedReportResource::collection($this->savedReports->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new AnalyticsSavedReportResource($this->savedReports->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $teamMember = $request->attributes->get('team_member');
        $report = $this->savedReports->create($data, $teamMember?->id);

        return ApiResponse::success(new AnalyticsSavedReportResource($report), 'Saved report created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validatePayload($request, partial: true);

        $report = $this->savedReports->update($this->scope->findSavedReport($id), $data);

        return ApiResponse::success(new AnalyticsSavedReportResource($report), 'Saved report updated');
    }

    public function archive(string $id): JsonResponse
    {
        $report = $this->savedReports->archive($this->scope->findSavedReport($id));

        return ApiResponse::success(new AnalyticsSavedReportResource($report), 'Saved report archived');
    }

    public function run(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $job = $this->exports->runSavedReport($this->scope->findSavedReport($id), $teamMember?->id);

        return ApiResponse::success(new AnalyticsExportJobResource($job), 'Export generated', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'report_type' => [$required, Rule::in(AnalyticsReportType::all())],
            'export_format' => ['nullable', Rule::in(AnalyticsExportFormat::all())],
            'filters' => ['nullable', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
            'filters.location_id' => ['nullable', 'uuid'],
            'filters.provider_id' => ['nullable', 'uuid'],
            'is_scheduled' => ['nullable', 'boolean'],
            'schedule_frequency' => ['nullable', Rule::in(AnalyticsScheduleFrequency::all())],
            'schedule_day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'schedule_day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'schedule_time' => ['nullable', 'string', 'max:10'],
            'delivery_emails' => ['nullable', 'array', 'max:10'],
            'delivery_emails.*' => ['email', 'max:255'],
        ]);
    }
}
