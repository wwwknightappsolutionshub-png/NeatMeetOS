<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Domains\Identity\Services\TenantSignupService;
use App\Shared\Support\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShellController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenantContext,
        TenantEntitlementService $entitlements,
        MemberPushDispatchService $push,
    ): JsonResponse {
        $tenant = $tenantContext->get() ?? $request->attributes->get('tenant');
        $user = $request->user();

        return ApiResponse::success([
            'authenticated' => $user !== null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ] : null,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ] : null,
            'features' => $entitlements->resolveFeatures($tenant instanceof Tenant ? $tenant : null),
            'locked_modules' => $entitlements->lockedModuleHints($tenant instanceof Tenant ? $tenant : null),
            'limits' => $entitlements->resolveLimits($tenant instanceof Tenant ? $tenant : null),
            'trial' => $this->trialPayload($tenant instanceof Tenant ? $tenant : null),
            'vapid_public_key' => $push->publicKey(),
            'workspace_surfaces' => array_values(array_filter([
                'admin',
                'desk',
                'provider',
                'portal',
                'book',
                $user?->is_platform_admin ? 'platform' : null,
            ])),
        ]);
    }

    /**
     * Free-trial day counter for every tenant during the first 30 days from activation.
     *
     * @return array{active: bool, day: int, total_days: int, ends_at: string|null, label: string}|null
     */
    private function trialPayload(?Tenant $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        $totalDays = TenantSignupService::TRIAL_DAYS;
        $anchor = $tenant->activated_at ?? $tenant->created_at ?? now();
        $elapsed = (int) $anchor->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay());
        $day = max(1, min($totalDays, $elapsed + 1));
        $active = $elapsed < $totalDays;

        $subscription = $tenant->subscription()->withoutGlobalScopes()->first();
        $trialEnds = $subscription?->trial_ends_at
            ?? $anchor->copy()->addDays($totalDays);

        return [
            'active' => $active,
            'day' => $day,
            'total_days' => $totalDays,
            'ends_at' => $trialEnds?->toIso8601String(),
            'label' => "You are on Day {$day} / {$totalDays}",
        ];
    }
}
