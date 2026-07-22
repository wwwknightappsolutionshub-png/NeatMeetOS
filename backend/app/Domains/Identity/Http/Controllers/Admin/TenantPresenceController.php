<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantPresenceService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantPresenceController extends Controller
{
    public function __construct(
        private readonly TenantPresenceService $presence,
    ) {}

    public function heartbeat(): JsonResponse
    {
        $tenant = $this->presence->heartbeat();

        return ApiResponse::success($this->presence->presencePayload($tenant));
    }
}
