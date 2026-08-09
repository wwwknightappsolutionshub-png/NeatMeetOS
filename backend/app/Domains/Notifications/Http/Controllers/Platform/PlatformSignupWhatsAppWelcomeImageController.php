<?php

namespace App\Domains\Notifications\Http\Controllers\Platform;

use App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public banner image Genius can fetch for signup welcome WhatsApp.
 */
class PlatformSignupWhatsAppWelcomeImageController extends Controller
{
    public function __construct(
        private readonly PlatformSignupWhatsAppWelcomeService $welcome,
    ) {}

    public function show(): Response
    {
        $binary = $this->welcome->resolveBannerBinary();
        if (! is_string($binary) || $binary === '') {
            return response('Signup welcome banner unavailable', 404);
        }

        return response($binary, 200, [
            'Content-Type' => $this->welcome->resolveBannerMime(),
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
