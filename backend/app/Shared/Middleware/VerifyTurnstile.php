<?php

namespace App\Shared\Middleware;

use App\Shared\Services\AbuseGuard;
use App\Shared\Services\TurnstileVerifier;
use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(
        private readonly TurnstileVerifier $verifier,
        private readonly AbuseGuard $abuse,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verifier->isEnabled()) {
            return $next($request);
        }

        $token = $request->input('turnstile_token')
            ?? $request->input('cf-turnstile-response')
            ?? $request->header('X-Turnstile-Token');

        if (! $this->verifier->verify(is_string($token) ? $token : null, $request->ip())) {
            $this->abuse->recordTurnstileFailure((string) $request->ip());

            return ApiResponse::error(
                'Security check failed. Please try again.',
                422,
                ['turnstile_token' => ['Security verification failed.']],
                'turnstile_failed',
            );
        }

        return $next($request);
    }
}
