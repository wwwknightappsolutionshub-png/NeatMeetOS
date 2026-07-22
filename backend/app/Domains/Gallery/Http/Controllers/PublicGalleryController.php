<?php

namespace App\Domains\Gallery\Http\Controllers;

use App\Domains\Gallery\Http\Resources\GalleryWorkResource;
use App\Domains\Gallery\Services\GalleryWorkService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

class PublicGalleryController extends Controller
{
    public function __construct(
        private readonly GalleryWorkService $works,
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = $this->tenantContext->get();

        if (! $this->entitlements->isEnabled($tenant, 'gallery')) {
            return ApiResponse::error(
                'Gallery is not available on this plan.',
                403,
                null,
                'feature_disabled',
                ['items' => []],
            );
        }

        return ApiResponse::success(
            GalleryWorkResource::collection($this->works->listPublished())->resolve(),
        );
    }
}
