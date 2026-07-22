<?php

namespace App\Domains\Memberships\Enums;

final class LoyaltyEntryType
{
    public const MANUAL_AWARD = 'manual_award';

    public const MANUAL_DEDUCTION = 'manual_deduction';

    public const MEMBERSHIP_BONUS = 'membership_bonus';

    public const PROMOTION = 'promotion';

    public const POS_EARN = 'pos_earn';

    public const POS_REDEEM = 'pos_redeem';

    public const ADJUSTMENT = 'adjustment';

    public const CHECKIN_VISIT = 'checkin_visit';

    public const CRM_JOIN_SIGNUP = 'crm_join_signup';

    public const REFERRAL_REFERRER = 'referral_referrer';

    public const REFERRAL_REFERRED = 'referral_referred';

    public static function all(): array
    {
        return [
            self::MANUAL_AWARD,
            self::MANUAL_DEDUCTION,
            self::MEMBERSHIP_BONUS,
            self::PROMOTION,
            self::POS_EARN,
            self::POS_REDEEM,
            self::ADJUSTMENT,
            self::CHECKIN_VISIT,
            self::CRM_JOIN_SIGNUP,
            self::REFERRAL_REFERRER,
            self::REFERRAL_REFERRED,
        ];
    }
}
