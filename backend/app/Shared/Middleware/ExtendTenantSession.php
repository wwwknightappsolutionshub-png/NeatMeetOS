<?php

namespace App\Shared\Middleware;

use App\Domains\Identity\Services\TenantSessionService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slide tenant admin Sanctum expiry forward on authenticated admin activity.
 */
class ExtendTenantSession
{
    public function __construct(
        private readonly TenantSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $this->sessions->touch($token, $user);
        }

        return $response;
    }
}
