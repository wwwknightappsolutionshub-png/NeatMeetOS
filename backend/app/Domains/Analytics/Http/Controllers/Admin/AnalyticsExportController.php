<?php

namespace App\Domains\Analytics\Http\Controllers\Admin;

use App\Domains\Analytics\Enums\AnalyticsExportFormat;
use App\Domains\Analytics\Enums\AnalyticsExportJobStatus;
use App\Domains\Analytics\Enums\AnalyticsReportType;
use App\Domains\Analytics\Http\Resources\AnalyticsExportJobResource;
use App\Domains\Analytics\Services\AnalyticsExportService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
    public function __construct(
        private readonly AnalyticsExportService $exports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'report_type' => ['nullable', Rule::in(AnalyticsReportType::all())],
            'status' => ['nullable', Rule::in(AnalyticsExportJobStatus::all())],
        ]);

        return ApiResponse::success(
            AnalyticsExportJobResource::collection($this->exports->list($filters)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', Rule::in(AnalyticsReportType::all())],
            'export_format' => ['nullable', Rule::in(AnalyticsExportFormat::all())],
            'filters' => ['nullable', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date'],
            'filters.location_id' => ['nullable', 'uuid'],
            'filters.provider_id' => ['nullable', 'uuid'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $job = $this->exports->createAdHoc($data, $teamMember?->id);

        return ApiResponse::success(new AnalyticsExportJobResource($job), 'Export generated', 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new AnalyticsExportJobResource($this->exports->find($id)));
    }

    public function download(string $id): StreamedResponse
    {
        $job = $this->exports->find($id);

        abort_unless(
            $job->status === AnalyticsExportJobStatus::COMPLETED && $job->file_path !== null,
            404,
            'Export is not available for download.',
        );

        $disk = \Illuminate\Support\Facades\Storage::disk($job->file_disk ?? 'local');

        abort_unless(
            $disk->exists($job->file_path),
            404,
            'Export file is no longer available.',
        );

        return $disk->download($job->file_path, $job->file_name);
    }
}
