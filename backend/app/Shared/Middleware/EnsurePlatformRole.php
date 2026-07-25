<?php

namespace App\Shared\Middleware;

use App\Domains\Identity\Support\PlatformRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformRole
{
    /**
     * @param  string  ...$roles  Allowed platform roles (owner|manager|support).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Platform administrator access required.',
            ], 403);
        }

        $effective = PlatformRole::effective(
            (bool) $user->is_platform_admin,
            $user->platform_role,
        );

        if ($roles !== [] && ! in_array($effective, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your platform role cannot perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
