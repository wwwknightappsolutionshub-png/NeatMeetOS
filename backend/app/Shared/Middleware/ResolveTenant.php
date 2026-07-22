<?php

namespace App\Shared\Middleware;

use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant !== null) {
            $this->tenantContext->set($tenant);
            $request->attributes->set('tenant', $tenant);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        if ($tenantId = $request->header('X-Tenant-ID')) {
            return Tenant::query()->where('id', $tenantId)->where('status', 'active')->first();
        }

        if ($slug = $request->header('X-Tenant-Slug')) {
            return Tenant::query()->where('slug', $slug)->where('status', 'active')->first();
        }

        $user = $request->user();

        if ($user !== null && $user->current_team_member?->tenant) {
            return $user->current_team_member->tenant;
        }

        return null;
    }
}
