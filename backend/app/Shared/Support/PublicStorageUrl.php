<?php

namespace App\Shared\Support;

final class PublicStorageUrl
{
    /**
     * Build an absolute public URL for a file on the public disk.
     */
    public static function fromDiskPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.$path;
    }

    /**
     * Normalize stored media URLs so /storage paths always use APP_URL
     * (fixes local URLs saved without the API port).
     */
    public static function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/storage/')) {
            return rtrim((string) config('app.url'), '/').$url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/')) {
            return rtrim((string) config('app.url'), '/').$path;
        }

        if (! str_contains($url, '://') && ! str_starts_with($url, '/')) {
            return self::fromDiskPath($url);
        }

        return $url;
    }
}
