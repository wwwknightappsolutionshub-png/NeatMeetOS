<?php

namespace App\Shared\Support;

final class Currency
{
    /** @var array<string, string> */
    public const COUNTRY_TO_CURRENCY = [
        'GB' => 'GBP',
        'UK' => 'GBP',
        'NG' => 'NGN',
        'US' => 'USD',
        'IE' => 'EUR',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'NL' => 'EUR',
        'ES' => 'EUR',
        'IT' => 'EUR',
        'CA' => 'CAD',
        'AU' => 'AUD',
        'NZ' => 'NZD',
        'ZA' => 'ZAR',
        'KE' => 'KES',
        'GH' => 'GHS',
        'IN' => 'INR',
    ];

    /** @var list<string> */
    public const SUPPORTED = [
        'GBP', 'NGN', 'USD', 'EUR', 'CAD', 'AUD', 'NZD', 'ZAR', 'KES', 'GHS', 'INR',
    ];

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return in_array($code, self::SUPPORTED, true) ? $code : 'GBP';
    }

    public static function fromCountry(?string $country): string
    {
        $country = strtoupper(trim((string) $country));

        return self::normalize(self::COUNTRY_TO_CURRENCY[$country] ?? 'GBP');
    }

    public static function symbol(string $code): string
    {
        return match (self::normalize($code)) {
            'GBP' => '£',
            'EUR' => '€',
            'USD', 'CAD', 'AUD', 'NZD' => '$',
            'NGN' => '₦',
            'ZAR' => 'R',
            'KES' => 'KSh',
            'GHS' => 'GH₵',
            'INR' => '₹',
            default => self::normalize($code).' ',
        };
    }

    public static function minorUnitLabel(string $code): string
    {
        return match (self::normalize($code)) {
            'GBP' => 'pence',
            'EUR' => 'cents',
            'USD', 'CAD', 'AUD', 'NZD' => 'cents',
            'NGN' => 'kobo',
            'ZAR' => 'cents',
            'KES' => 'cents',
            'GHS' => 'pesewas',
            'INR' => 'paise',
            default => 'minor units',
        };
    }
}
