<?php

namespace App\Domains\Notifications\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Notifications\Services\NotificationReportingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationReportingController extends Controller
{
    public function __construct(
        private readonly NotificationReportingService $reportingService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->summary($from, $to));
    }

    public function failures(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->failures($from, $to));
    }

    public function byPurpose(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($this->reportingService->byPurpose($from, $to));
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return [
            ! empty($data['from']) ? Carbon::parse($data['from']) : null,
            ! empty($data['to']) ? Carbon::parse($data['to']) : null,
        ];
    }
}
