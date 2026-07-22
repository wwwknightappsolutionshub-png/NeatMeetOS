<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\PlatformReferralProgramService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class TenantPlatformReferralController extends Controller
{
    public function __construct(
        private readonly PlatformReferralProgramService $program,
        private readonly TenantContext $tenantContext,
    ) {}

    public function show(): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            abort(422, 'Tenant context required');
        }

        return ApiResponse::success($this->program->tenantSharePayload($tenant));
    }
}
