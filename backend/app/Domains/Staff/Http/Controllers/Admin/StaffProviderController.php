<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Staff\Http\Resources\StaffProviderResource;
use App\Domains\Staff\Services\StaffProviderService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StaffProviderController extends Controller
{
    public function __construct(private readonly StaffProviderService $providerService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(StaffProviderResource::collection($this->providerService->list()));
    }

    public function show(string $teamMemberId): JsonResponse
    {
        return ApiResponse::success(new StaffProviderResource($this->providerService->show($teamMemberId)));
    }
}
