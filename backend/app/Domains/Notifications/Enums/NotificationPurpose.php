<?php

namespace App\Domains\Notifications\Enums;

final class NotificationPurpose
{
    public const BOOKING_CONFIRMATION = 'booking_confirmation';

    public const BOOKING_REMINDER = 'booking_reminder';

    public const BOOKING_CANCELLATION = 'booking_cancellation';

    public const WAITLIST_CONTACT = 'waitlist_contact';

    public const PAYMENT_LINK = 'payment_link';

    public const PAYMENT_REMINDER = 'payment_reminder';

    public const MEMBERSHIP_RENEWAL_NOTICE = 'membership_renewal_notice';

    public const MEMBERSHIP_EXPIRY_NOTICE = 'membership_expiry_notice';

    public const MANUAL_CLIENT_MESSAGE = 'manual_client_message';

    public const INTERNAL_NOTE_DELIVERY = 'internal_note_delivery';

    public const CRM_JOIN_WELCOME = 'crm_join_welcome';

    public const REFERRAL_THANK_YOU = 'referral_thank_you';

    public const REFERRAL_INVITE = 'referral_invite';

    public static function all(): array
    {
        return [
            self::BOOKING_CONFIRMATION,
            self::BOOKING_REMINDER,
            self::BOOKING_CANCELLATION,
            self::WAITLIST_CONTACT,
            self::PAYMENT_LINK,
            self::PAYMENT_REMINDER,
            self::MEMBERSHIP_RENEWAL_NOTICE,
            self::MEMBERSHIP_EXPIRY_NOTICE,
            self::MANUAL_CLIENT_MESSAGE,
            self::INTERNAL_NOTE_DELIVERY,
            self::CRM_JOIN_WELCOME,
            self::REFERRAL_THANK_YOU,
            self::REFERRAL_INVITE,
        ];
    }

    /**
     * Map a purpose to the preference category that gates it.
     */
    public static function preferenceCategory(string $purpose): string
    {
        return match ($purpose) {
            self::BOOKING_CONFIRMATION, self::BOOKING_REMINDER, self::BOOKING_CANCELLATION, self::WAITLIST_CONTACT => NotificationPreferenceCategory::BOOKING,
            self::PAYMENT_LINK, self::PAYMENT_REMINDER => NotificationPreferenceCategory::PAYMENT,
            self::MEMBERSHIP_RENEWAL_NOTICE, self::MEMBERSHIP_EXPIRY_NOTICE => NotificationPreferenceCategory::MEMBERSHIP,
            self::CRM_JOIN_WELCOME, self::REFERRAL_THANK_YOU, self::REFERRAL_INVITE => NotificationPreferenceCategory::GENERAL,
            default => NotificationPreferenceCategory::GENERAL,
        };
    }
}
