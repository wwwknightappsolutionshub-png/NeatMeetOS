<?php

namespace App\Shared\Support;

final class PhoneNormalizer
{
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $trimmed = trim($raw);
        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return $digits;
    }

    public static function digitCount(?string $raw): int
    {
        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return 0;
        }

        return strlen(preg_replace('/\D/', '', $normalized) ?? '');
    }

    public static function isValid(?string $raw): bool
    {
        return self::digitCount($raw) >= 7;
    }
}
