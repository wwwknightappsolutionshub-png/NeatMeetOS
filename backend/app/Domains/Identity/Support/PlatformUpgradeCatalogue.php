<?php

namespace App\Domains\Identity\Support;

final class PlatformUpgradeCatalogue
{
    /**
     * @return list<array{key: string, label: string, use_case: string}>
     */
    public static function unlocksForPath(string $path): array
    {
        return match ($path) {
            'basic_to_pro' => [
                [
                    'key' => 'pos',
                    'label' => 'POS checkout',
                    'use_case' => 'Take payment at the chair and close the ticket before the next client sits down.',
                ],
                [
                    'key' => 'inventory',
                    'label' => 'Inventory',
                    'use_case' => 'Know when colour and retail are low before you run out mid-week.',
                ],
                [
                    'key' => 'memberships',
                    'label' => 'Memberships & packages',
                    'use_case' => 'Sell recurring plans and packages that keep chairs booked.',
                ],
                [
                    'key' => 'analytics',
                    'label' => 'Analytics',
                    'use_case' => 'See which services and days actually drive revenue.',
                ],
                [
                    'key' => 'notifications',
                    'label' => 'Notifications centre',
                    'use_case' => 'Keep the team aligned with clearer salon alerts.',
                ],
            ],
            'pro_to_diamond' => [
                [
                    'key' => 'marketing',
                    'label' => 'Marketing automations',
                    'use_case' => 'Fill empty slots with rebooking and win-back campaigns.',
                ],
                [
                    'key' => 'integrations',
                    'label' => 'Integrations',
                    'use_case' => 'Connect the tools your multi-location brand already uses.',
                ],
                [
                    'key' => 'ecommerce',
                    'label' => 'Online shop',
                    'use_case' => 'Sell retail after hours without another staffed channel.',
                ],
                [
                    'key' => 'analytics',
                    'label' => 'Advanced analytics',
                    'use_case' => 'Compare locations and spot underperforming chairs early.',
                ],
            ],
            default => [],
        };
    }

    public static function targetPlanSlug(string $path): string
    {
        return match ($path) {
            'basic_to_pro' => 'pro',
            'pro_to_diamond' => 'diamond',
            default => 'pro',
        };
    }

    public static function targetPlanName(string $path): string
    {
        return match ($path) {
            'basic_to_pro' => 'Pro',
            'pro_to_diamond' => 'Diamond',
            default => 'Pro',
        };
    }

    public static function pathForPlanSlug(?string $slug): ?string
    {
        return match ($slug) {
            'basic' => 'basic_to_pro',
            'pro' => 'pro_to_diamond',
            default => null,
        };
    }
}
