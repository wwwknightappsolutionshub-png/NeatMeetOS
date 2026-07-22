<?php

namespace App\Domains\Marketing\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Marketing\Services\MarketingReportingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketingReportingController extends Controller
{
    public function __construct(
        private readonly MarketingReportingService $reportingService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->summary($from, $to));
    }

    public function campaigns(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->campaigns($from, $to));
    }

    public function runs(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->runs($from, $to));
    }

    public function automationSummary(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->automationSummary($from, $to));
    }

    public function automationWorkflows(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->automationWorkflows($from, $to));
    }

    public function automationExecutions(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->automationExecutions($from, $to));
    }

    public function automationMessages(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->automationMessages($from, $to));
    }

    public function automationSuppressions(): JsonResponse
    {
        return ApiResponse::success($this->reportingService->automationSuppressions());
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'location_id' => ['nullable', 'uuid'],
            'marketing_campaign_id' => ['nullable', 'uuid'],
        ]);

        return [
            ! empty($data['from']) ? Carbon::parse($data['from']) : null,
            ! empty($data['to']) ? Carbon::parse($data['to']) : null,
        ];
    }
}
