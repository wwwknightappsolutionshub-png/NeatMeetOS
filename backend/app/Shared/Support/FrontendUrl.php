<?php

namespace App\Shared\Support;

/**
 * Absolute public frontend URLs for emails and deep links.
 */
final class FrontendUrl
{
    /**
     * Build an absolute frontend URL. Never returns a relative path.
     */
    public static function to(string $path = '/'): string
    {
        $base = rtrim(trim((string) config('app.frontend_url')), '/');
        if ($base === '') {
            $base = rtrim(trim((string) config('app.url')), '/');
        }
        if ($base === '') {
            $base = 'http://localhost:3000';
        }

        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }

    public static function memberApp(string $tenantSlug): string
    {
        $slug = trim($tenantSlug);
        if ($slug === '') {
            return self::to('/member');
        }

        return self::to('/member/'.rawurlencode($slug));
    }

    public static function bookingPage(string $tenantSlug): string
    {
        $slug = trim($tenantSlug);
        if ($slug === '') {
            return self::to('/book');
        }

        return self::to('/book/'.rawurlencode($slug));
    }
}
