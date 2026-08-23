<?php

namespace App\Shared\Middleware;

use App\Shared\Services\AbuseGuard;
use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockBannedIp
{
    public function __construct(private readonly AbuseGuard $abuse) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();

        if ($this->abuse->isBanned($ip)) {
            return ApiResponse::error(
                'Access temporarily blocked due to suspicious activity.',
                403,
                null,
                'ip_banned',
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() === 429) {
            $this->abuse->recordThrottleHit($ip);
        }

        return $response;
    }
}
