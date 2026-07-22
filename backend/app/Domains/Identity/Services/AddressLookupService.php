<?php

namespace App\Domains\Identity\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * UK postcode → address suggestions (postcodes.io + OpenStreetMap Nominatim).
 */
class AddressLookupService
{
    /**
     * @return array{
     *     postcode: string,
     *     formatted_postcode: string|null,
     *     suggestions: list<array{label: string, address_line1: string, city: string, postcode: string, country: string}>
     * }
     */
    public function lookup(string $postcode): array
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
        if (strlen($normalized) < 5) {
            throw ValidationException::withMessages([
                'postcode' => ['Enter a fuller postcode to look up addresses.'],
            ]);
        }

        $pcResponse = Http::timeout(8)
            ->acceptJson()
            ->get('https://api.postcodes.io/postcodes/'.$normalized);

        $formatted = null;
        $cityHint = null;
        if ($pcResponse->successful() && is_array($pcResponse->json('result'))) {
            $result = $pcResponse->json('result');
            $formatted = (string) ($result['postcode'] ?? '');
            $cityHint = (string) (
                $result['admin_district']
                ?? $result['parish']
                ?? $result['region']
                ?? ''
            );
        }

        $queryPostcode = $formatted ?: trim($postcode);
        $nominatim = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'NeatMeetOS/1.0 (salon signup address lookup)',
                'Accept' => 'application/json',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'postalcode' => $queryPostcode,
                'countrycodes' => 'gb',
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 8,
            ]);

        $suggestions = [];
        if ($nominatim->successful() && is_array($nominatim->json())) {
            foreach ($nominatim->json() as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $address = is_array($row['address'] ?? null) ? $row['address'] : [];
                $line1 = $this->buildLine1($address, (string) ($row['display_name'] ?? ''));
                $city = (string) (
                    $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['suburb']
                    ?? $cityHint
                    ?? ''
                );
                $pc = (string) ($address['postcode'] ?? $formatted ?? $queryPostcode);
                if ($line1 === '' && $city === '') {
                    continue;
                }
                $label = trim($line1.($city !== '' ? ', '.$city : '').($pc !== '' ? ' '.$pc : ''));
                $suggestions[] = [
                    'label' => $label,
                    'address_line1' => $line1 !== '' ? $line1 : ($city !== '' ? $city : $label),
                    'city' => $city !== '' ? $city : ($cityHint ?: ''),
                    'postcode' => $pc,
                    'country' => 'GB',
                ];
            }
        }

        // Deduplicate by label.
        $unique = [];
        $seen = [];
        foreach ($suggestions as $suggestion) {
            $key = strtolower($suggestion['label']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $suggestion;
        }

        if ($unique === [] && ($formatted || $cityHint)) {
            $unique[] = [
                'label' => trim(($cityHint ?: 'UK').' '.($formatted ?: $queryPostcode)),
                'address_line1' => $cityHint ?: '',
                'city' => $cityHint ?: '',
                'postcode' => $formatted ?: $queryPostcode,
                'country' => 'GB',
            ];
        }

        if ($unique === []) {
            throw ValidationException::withMessages([
                'postcode' => ['No addresses found for that postcode. Check it and try again.'],
            ]);
        }

        return [
            'postcode' => $postcode,
            'formatted_postcode' => $formatted,
            'suggestions' => $unique,
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function buildLine1(array $address, string $displayName): string
    {
        $house = trim((string) ($address['house_number'] ?? $address['house_name'] ?? ''));
        $road = trim((string) ($address['road'] ?? $address['pedestrian'] ?? ''));
        if ($house !== '' && $road !== '') {
            return $house.' '.$road;
        }
        if ($road !== '') {
            return $road;
        }

        // Fall back to first segment of display name.
        $parts = array_map('trim', explode(',', $displayName));

        return $parts[0] ?? '';
    }
}
