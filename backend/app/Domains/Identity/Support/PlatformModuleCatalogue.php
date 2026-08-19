<?php

namespace App\Domains\Identity\Support;

/**
 * Canonical tenant product modules gated by subscription plan + per-tenant overrides.
 */
final class PlatformModuleCatalogue
{
    /**
     * @return list<array{key: string, label: string, description: string, core: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'booking',
                'label' => 'Booking',
                'description' => 'Appointments, services catalogue, and calendar.',
                'core' => false,
            ],
            [
                'key' => 'crm',
                'label' => 'CRM / Clients',
                'description' => 'Client profiles, notes, consents, and timeline.',
                'core' => false,
            ],
            [
                'key' => 'payments',
                'label' => 'Payments',
                'description' => 'Payment records, refunds, and reporting.',
                'core' => false,
            ],
            [
                'key' => 'money',
                'label' => 'My money',
                'description' => 'Simple notebook: what you took, what you spent, and what’s left.',
                'core' => false,
            ],
            [
                'key' => 'pos',
                'label' => 'POS',
                'description' => 'In-salon checkout and receipts.',
                'core' => false,
            ],
            [
                'key' => 'inventory',
                'label' => 'Inventory',
                'description' => 'Stock, adjustments, and product catalogue.',
                'core' => false,
            ],
            [
                'key' => 'memberships',
                'label' => 'Memberships',
                'description' => 'Membership offers, entitlements, and billing.',
                'core' => false,
            ],
            [
                'key' => 'marketing',
                'label' => 'Marketing',
                'description' => 'Campaigns, reminders, and outbound runs.',
                'core' => false,
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'description' => 'In-app and channel notification centre for the salon.',
                'core' => false,
            ],
            [
                'key' => 'analytics',
                'label' => 'Analytics',
                'description' => 'Dashboards and performance reporting.',
                'core' => false,
            ],
            [
                'key' => 'integrations',
                'label' => 'Integrations',
                'description' => 'Third-party connectors and webhooks.',
                'core' => false,
            ],
            [
                'key' => 'ecommerce',
                'label' => 'Shop / Ecommerce',
                'description' => 'Online shop catalogue and orders.',
                'core' => false,
            ],
            [
                'key' => 'gallery',
                'label' => 'Works Gallery',
                'description' => 'Instagram-style portfolio of previous client work.',
                'core' => false,
            ],
            [
                'key' => 'lookbook',
                'label' => 'Lookbook',
                'description' => 'Curated branded lookbook with seeded category imagery.',
                'core' => false,
            ],
            [
                'key' => 'next_visit',
                'label' => 'Next Visit',
                'description' => 'Check-in prompt to schedule the next visit with reminders.',
                'core' => false,
            ],
            [
                'key' => 'ai_hairstyle',
                'label' => 'AI Hairstyle Preview',
                'description' => 'Premium selfie-to-look preview for barbershops and salons. Super-admin enable only.',
                'core' => false,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * Map RBAC permission module prefix → product feature key.
     * identity + staff are always available (core salon ops).
     *
     * @return array<string, string>
     */
    public static function permissionModuleMap(): array
    {
        return [
            'crm' => 'crm',
            'booking' => 'booking',
            'payments' => 'payments',
            'money' => 'money',
            'inventory' => 'inventory',
            'ecommerce' => 'ecommerce',
            'pos' => 'pos',
            'memberships' => 'memberships',
            'marketing' => 'marketing',
            'notifications' => 'notifications',
            'analytics' => 'analytics',
            'integrations' => 'integrations',
            'gallery' => 'gallery',
            'lookbook' => 'lookbook',
            'next_visit' => 'next_visit',
            'ai_hairstyle' => 'ai_hairstyle',
        ];
    }

    /**
     * Default feature map when a plan has no features JSON.
     *
     * @return array<string, bool>
     */
    public static function defaultsForPlanSlug(string $slug): array
    {
        $allOff = array_fill_keys(self::keys(), false);
        $allOff['booking'] = true;
        $allOff['crm'] = true;
        $allOff['payments'] = true;
        $allOff['money'] = true;

        return match ($slug) {
            'basic' => array_merge($allOff, [
                'notifications' => false,
                'pos' => false,
                'inventory' => false,
                'memberships' => false,
                'marketing' => false,
                'analytics' => false,
                'integrations' => false,
                'ecommerce' => false,
                'gallery' => false,
                'lookbook' => false,
                'next_visit' => false,
                'ai_hairstyle' => false,
            ]),
            'pro' => array_merge($allOff, [
                'notifications' => true,
                'pos' => true,
                'inventory' => true,
                'memberships' => true,
                'analytics' => true,
                'marketing' => false,
                'integrations' => false,
                'ecommerce' => false,
                'gallery' => true,
                'lookbook' => true,
                'next_visit' => true,
                'ai_hairstyle' => false,
            ]),
            // Premium module is never plan-included; platform override + trial only.
            'diamond' => array_merge(array_fill_keys(self::keys(), true), [
                'ai_hairstyle' => false,
            ]),
            default => $allOff,
        };
    }
}
