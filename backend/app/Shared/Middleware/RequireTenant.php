<?php

namespace App\Shared\Middleware;

use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures ResolveTenant has established an active tenant (required for public book APIs).
 */
class RequireTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->id() === null) {
            abort(400, 'Tenant is required. Send X-Tenant-Slug or X-Tenant-ID.');
        }

        return $next($request);
    }
}
