<?php

namespace App\Domains\Identity\Support;

/**
 * Progressive trial unlocks before plan entitlements fully apply.
 *
 * - CRM: unlocked until 30 contacts; day-21 offer email at 20 contacts
 * - Booking: unlocked until a single booking is worth £500; then gate + day-21 email
 * - inventory / pos / memberships / marketing / notifications / lookbook / next_visit: 30 days
 * - gallery: 30 days only for female-oriented business types
 */
final class ProgressiveModuleAccess
{
    public const CRM_GATE_CONTACTS = 30;

    public const CRM_NUDGE_CONTACTS = 20;

    /** Single appointment total (service lines) that gates booking during trial. */
    public const BOOKING_GATE_CENTS = 50000;

    public const TIME_UNLOCK_DAYS = 30;

    public const TRIGGER_CONTACTS_20 = 'usage_contacts_20';

    public const TRIGGER_BOOKING_500 = 'usage_booking_500';

    /** Business types that receive a free gallery trial. */
    public const GALLERY_FEMALE_ORIENTED_TYPES = ['boutique', 'chain', 'spa'];

    /**
     * @return list<string>
     */
    public static function timeUnlockModules(): array
    {
        return [
            'inventory',
            'pos',
            'memberships',
            'marketing',
            'notifications',
            'lookbook',
            'next_visit',
        ];
    }

    public static function isGalleryFemaleOriented(?string $businessType): bool
    {
        return in_array((string) $businessType, self::GALLERY_FEMALE_ORIENTED_TYPES, true);
    }
}
