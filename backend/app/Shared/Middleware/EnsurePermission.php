<?php

namespace App\Shared\Middleware;

use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Identity\Support\PlatformModuleCatalogue;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly TenantEntitlementService $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = $request->attributes->get('permissions', []);

        if (! in_array($permission, $permissions, true)) {
            return ApiResponse::error('Forbidden', 403);
        }

        $modulePrefix = explode('.', $permission)[0] ?? '';
        $featureMap = PlatformModuleCatalogue::permissionModuleMap();
        if (isset($featureMap[$modulePrefix])) {
            $tenant = $this->tenantContext->get() ?? $request->attributes->get('tenant');
            $moduleKey = $featureMap[$modulePrefix];

            // Day-board operations can lock while the service catalogue stays available.
            $path = $request->path();
            if (
                $moduleKey === 'booking'
                && (
                    str_contains($path, 'booking-board')
                    || str_contains($path, 'walk-ins')
                    || str_contains($path, 'waitlist')
                    || (str_contains($path, 'appointments') && ! str_contains($path, 'booking-services'))
                )
            ) {
                $moduleKey = 'booking_board';
            }

            if (! $this->entitlements->isEnabled($tenant, $moduleKey)) {
                $payloadKey = $moduleKey === 'booking_board' ? 'booking' : $moduleKey;
                $payload = $this->entitlements->upgradeRequiredPayload($payloadKey);
                if ($moduleKey === 'booking_board') {
                    $payload['module'] = 'booking_board';
                    $payload['module_label'] = 'Booking board';
                }
                $plans = collect($payload['available_on'])->pluck('name')->filter()->values();
                $message = $plans->isNotEmpty()
                    ? sprintf(
                        '%s is available on %s. Upgrade your plan to unlock this feature.',
                        $payload['module_label'],
                        $plans->join(', ', ' and '),
                    )
                    : sprintf(
                        '%s is not included in your current plan. Contact platform support to enable it.',
                        $payload['module_label'],
                    );

                return ApiResponse::error(
                    $message,
                    403,
                    null,
                    'module_upgrade_required',
                    $payload,
                );
            }
        }

        return $next($request);
    }
}
