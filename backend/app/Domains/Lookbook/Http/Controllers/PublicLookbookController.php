<?php

namespace App\Domains\Lookbook\Http\Controllers;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Lookbook\Http\Resources\LookbookItemResource;
use App\Domains\Lookbook\Services\LookbookItemService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class PublicLookbookController extends Controller
{
    public function __construct(
        private readonly LookbookItemService $items,
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->get();

        if (! $this->entitlements->isEnabled($tenant, 'lookbook')) {
            return ApiResponse::error(
                'Lookbook is not available on this plan.',
                403,
                null,
                'feature_disabled',
                ['items' => []],
            );
        }

        return ApiResponse::success(
            LookbookItemResource::collection($this->items->listPublished())->resolve(),
        );
    }
}
