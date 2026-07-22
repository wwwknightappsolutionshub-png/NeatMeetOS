<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use Illuminate\Validation\ValidationException;

final class ProviderDriverCompatibility
{
    /**
     * @var array<string, array<int, string>>
     */
    private const DRIVER_CATEGORIES = [
        ProviderDriver::SIMULATION => [
            ProviderCategory::EMAIL,
            ProviderCategory::SMS,
            ProviderCategory::PAYMENT_GATEWAY,
            ProviderCategory::GIFT_CARD,
            ProviderCategory::GENERIC_WEBHOOK,
        ],
        ProviderDriver::MANUAL => [
            ProviderCategory::EMAIL,
            ProviderCategory::SMS,
            ProviderCategory::PAYMENT_GATEWAY,
        ],
        ProviderDriver::MAILGUN => [ProviderCategory::EMAIL],
        ProviderDriver::TWILIO => [ProviderCategory::SMS],
        ProviderDriver::STRIPE => [ProviderCategory::PAYMENT_GATEWAY],
        ProviderDriver::CUSTOM => [
            ProviderCategory::EMAIL,
            ProviderCategory::SMS,
            ProviderCategory::PAYMENT_GATEWAY,
            ProviderCategory::GIFT_CARD,
            ProviderCategory::GENERIC_WEBHOOK,
        ],
    ];

    public function isCompatible(string $category, string $driver): bool
    {
        $allowed = self::DRIVER_CATEGORIES[$driver] ?? [];

        return in_array($category, $allowed, true);
    }

    public function assertCompatible(string $category, string $driver): void
    {
        if ($this->isCompatible($category, $driver)) {
            return;
        }

        throw ValidationException::withMessages([
            'driver' => ["Driver {$driver} is not compatible with category {$category}."],
        ]);
    }
}
