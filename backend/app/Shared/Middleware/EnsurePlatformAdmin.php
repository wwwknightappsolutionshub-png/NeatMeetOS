<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Platform administrator access required.',
            ], 403);
        }

        return $next($request);
    }
}
