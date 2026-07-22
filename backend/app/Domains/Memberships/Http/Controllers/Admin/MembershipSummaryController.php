<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Services\MembershipReportingService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MembershipSummaryController extends Controller
{
    public function __construct(private readonly MembershipReportingService $reportingService) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->reportingService->summary());
    }
}
