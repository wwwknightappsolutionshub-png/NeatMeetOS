<?php

namespace App\Domains\Identity\Http\Controllers\Platform;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\SubscriptionPlan;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Identity\Support\PlatformModuleCatalogue;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformModuleController extends Controller
{
    public function __construct(
        private readonly TenantEntitlementService $entitlements,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->entitlements->platformModulesIndex());
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);

        $keys = PlatformModuleCatalogue::keys();
        $data = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
            'limits' => ['nullable', 'array'],
            'limits.max_locations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'limits.max_staff' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'limits.max_workspaces' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        foreach (array_keys($data['features']) as $key) {
            if (! in_array($key, $keys, true)) {
                return ApiResponse::error("Unknown module key: {$key}", 422);
            }
        }

        $updated = $this->entitlements->updatePlanModules(
            $plan,
            $data['features'],
            $data['limits'] ?? null,
        );

        return ApiResponse::success([
            'id' => $updated->id,
            'name' => $updated->name,
            'slug' => $updated->slug,
            'features' => $this->entitlements->featuresForPlan($updated),
            'limits' => $updated->limits ?? [],
        ], 'Plan modules updated');
    }

    public function showTenant(string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        return ApiResponse::success($this->entitlements->tenantModules($tenant));
    }

    public function updateTenant(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($id);

        $data = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*' => ['nullable', 'boolean'],
        ]);

        foreach (array_keys($data['overrides']) as $key) {
            if (! PlatformModuleCatalogue::isValid((string) $key)) {
                return ApiResponse::error("Unknown module key: {$key}", 422);
            }
        }

        $result = $this->entitlements->syncTenantOverrides($tenant, $data['overrides']);

        return ApiResponse::success($result, 'Tenant modules updated');
    }
}
