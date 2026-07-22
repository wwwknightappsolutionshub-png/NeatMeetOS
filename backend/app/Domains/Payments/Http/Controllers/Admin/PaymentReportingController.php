<?php

namespace App\Domains\Payments\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Payments\Http\Resources\PaymentTransactionResource;
use App\Domains\Payments\Services\PaymentReportingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentReportingController extends Controller
{
    public function __construct(private readonly PaymentReportingService $reportingService) {}

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success($this->reportingService->summary($filters));
    }

    public function failed(Request $request): JsonResponse
    {
        $filters = $request->validate(['from' => ['nullable', 'date']]);

        return ApiResponse::success(
            PaymentTransactionResource::collection($this->reportingService->failed($filters)),
        );
    }

    public function deposits(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'lifecycle_state' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->reportingService->deposits($filters));
    }
}
