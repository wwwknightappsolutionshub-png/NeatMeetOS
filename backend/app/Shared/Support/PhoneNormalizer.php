<?php

namespace App\Shared\Support;

final class PhoneNormalizer
{
    /**
     * Normalize to a comparable E.164-ish string.
     * Accepts +44…, 0044…, and common UK national forms (07… / 7…).
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $compact = preg_replace('/[^\d+]/', '', trim($raw)) ?? '';
        if ($compact === '') {
            return '';
        }

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        if (str_starts_with($compact, '+')) {
            $rest = preg_replace('/\D/', '', substr($compact, 1)) ?? '';

            return $rest === '' ? '' : '+'.$rest;
        }

        $national = preg_replace('/\D/', '', $compact) ?? '';
        if ($national === '') {
            return '';
        }

        // UK national → E.164
        if (str_starts_with($national, '0') && strlen($national) >= 10) {
            return '+44'.substr($national, 1);
        }
        if (preg_match('/^7\d{9}$/', $national) === 1) {
            return '+44'.$national;
        }

        return $national;
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
