<?php

namespace App\Domains\Analytics\Http\Controllers\Admin;

use App\Domains\Analytics\Services\AnalyticsDateRangeResolver;
use App\Domains\Analytics\Services\AnalyticsOverviewService;
use App\Domains\Analytics\Services\AnalyticsScopeValidator;
use App\Domains\Analytics\Services\BookingAnalyticsService;
use App\Domains\Analytics\Services\ClientAnalyticsService;
use App\Domains\Analytics\Services\CommunicationsAnalyticsService;
use App\Domains\Analytics\Services\InventoryAnalyticsService;
use App\Domains\Analytics\Services\RevenueAnalyticsService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only admin analytics endpoints. All routes require analytics.view or
 * analytics.reporting.view and are tenant-scoped via AnalyticsScopeValidator.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsScopeValidator $scope,
        private readonly AnalyticsDateRangeResolver $dateRangeResolver,
        private readonly AnalyticsOverviewService $overviewService,
        private readonly BookingAnalyticsService $bookingAnalytics,
        private readonly RevenueAnalyticsService $revenueAnalytics,
        private readonly ClientAnalyticsService $clientAnalytics,
        private readonly InventoryAnalyticsService $inventoryAnalytics,
        private readonly CommunicationsAnalyticsService $communicationsAnalytics,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        [$range, $locationId, $providerId] = $this->filters($request);

        return ApiResponse::success(
            $this->overviewService->report($this->scope->tenantId(), $range, $locationId, $providerId),
        );
    }

    public function bookings(Request $request): JsonResponse
    {
        [$range, $locationId, $providerId] = $this->filters($request);

        return ApiResponse::success(
            $this->bookingAnalytics->report($this->scope->tenantId(), $range, $locationId, $providerId),
        );
    }

    public function revenue(Request $request): JsonResponse
    {
        [$range, $locationId, $providerId] = $this->filters($request);

        return ApiResponse::success(
            $this->revenueAnalytics->report($this->scope->tenantId(), $range, $locationId, $providerId),
        );
    }

    public function clients(Request $request): JsonResponse
    {
        [$range, $locationId] = $this->filters($request, includeProvider: false);

        return ApiResponse::success(
            $this->clientAnalytics->report($this->scope->tenantId(), $range, $locationId),
        );
    }

    public function inventory(Request $request): JsonResponse
    {
        [$range, $locationId] = $this->filters($request, includeProvider: false);

        return ApiResponse::success(
            $this->inventoryAnalytics->report($this->scope->tenantId(), $range, $locationId),
        );
    }

    public function communications(Request $request): JsonResponse
    {
        [$range] = $this->filters($request, includeLocation: false, includeProvider: false);

        return ApiResponse::success(
            $this->communicationsAnalytics->report($this->scope->tenantId(), $range),
        );
    }

    /**
     * @return array{0: \App\Domains\Analytics\DTOs\DateRange, 1: string|null, 2: string|null}
     */
    private function filters(Request $request, bool $includeLocation = true, bool $includeProvider = true): array
    {
        $rules = [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];

        if ($includeLocation) {
            $rules['location_id'] = ['nullable', 'uuid'];
        }
        if ($includeProvider) {
            $rules['provider_id'] = ['nullable', 'uuid'];
        }

        $data = $request->validate($rules);

        $range = $this->dateRangeResolver->resolve($data['from'] ?? null, $data['to'] ?? null);

        return [
            $range,
            $includeLocation ? ($data['location_id'] ?? null) : null,
            $includeProvider ? ($data['provider_id'] ?? null) : null,
        ];
    }
}
