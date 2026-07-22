<?php

namespace App\Domains\Identity\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Http\Resources\BrandingResource;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\BrandingService;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $brandingService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(new BrandingResource($this->brandingService->get()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_display_name' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:32', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'max:32', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'receipt_display_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'hero_emblem_mode' => ['nullable', 'string', Rule::in(Tenant::HERO_EMBLEM_MODES)],
            'hero_emblem_url' => ['nullable', 'string', 'max:2048'],
            'hero_image_url' => ['nullable', 'string', 'max:2048'],
            'store_status' => ['nullable', 'string', Rule::in(Tenant::STORE_STATUSES)],
            'social_facebook_url' => ['nullable', 'string', 'max:2048'],
            'social_instagram_url' => ['nullable', 'string', 'max:2048'],
            'social_tiktok_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $branding = $this->brandingService->update($data);

        return ApiResponse::success(new BrandingResource($branding), 'Branding updated');
    }

    public function uploadEmblem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $tenant = $this->tenantContext->get();
        $tenantId = $tenant?->id ?? 'shared';
        $path = $data['image']->store('branding/'.$tenantId.'/emblems', 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        $branding = $this->brandingService->update([
            'hero_emblem_mode' => 'custom',
            'hero_emblem_url' => $url,
        ]);

        return ApiResponse::success([
            'url' => $url,
            'path' => $path,
            'branding' => (new BrandingResource($branding))->resolve(),
        ], 'Emblem uploaded and saved', 201);
    }

    public function uploadHeroImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $tenant = $this->tenantContext->get();
        $tenantId = $tenant?->id ?? 'shared';
        $path = $data['image']->store('branding/'.$tenantId.'/heroes', 'public');
        $url = PublicStorageUrl::fromDiskPath($path);

        $branding = $this->brandingService->update([
            'hero_image_url' => $url,
        ]);

        return ApiResponse::success([
            'url' => $url,
            'path' => $path,
            'branding' => (new BrandingResource($branding))->resolve(),
        ], 'Hero image uploaded and saved', 201);
    }
}
