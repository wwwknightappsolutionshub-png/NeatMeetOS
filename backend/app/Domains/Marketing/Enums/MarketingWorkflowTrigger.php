<?php

namespace App\Domains\Marketing\Enums;

final class MarketingWorkflowTrigger
{
    public const CLIENT_CREATED = 'client_created';

    public const CONSENT_GRANTED = 'consent_granted';

    public const CONSENT_WITHDRAWN = 'consent_withdrawn';

    public const APPOINTMENT_COMPLETED = 'appointment_completed';

    public const APPOINTMENT_NO_SHOW = 'appointment_no_show';

    public const BIRTHDAY = 'birthday';

    public const MEMBERSHIP_STARTED = 'membership_started';

    public const MEMBERSHIP_CANCELLED = 'membership_cancelled';

    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [
            self::CLIENT_CREATED,
            self::CONSENT_GRANTED,
            self::CONSENT_WITHDRAWN,
            self::APPOINTMENT_COMPLETED,
            self::APPOINTMENT_NO_SHOW,
            self::BIRTHDAY,
            self::MEMBERSHIP_STARTED,
            self::MEMBERSHIP_CANCELLED,
            self::MANUAL,
        ];
    }

    /**
     * Triggers that fire automatically from another domain event (vs. admin/manual runs).
     */
    public static function eventDriven(): array
    {
        return [
            self::CLIENT_CREATED,
            self::CONSENT_GRANTED,
            self::CONSENT_WITHDRAWN,
            self::APPOINTMENT_COMPLETED,
            self::APPOINTMENT_NO_SHOW,
            self::MEMBERSHIP_STARTED,
            self::MEMBERSHIP_CANCELLED,
        ];
    }
}
