<?php

namespace App\Domains\Marketing\Enums;

final class MarketingCampaignType
{
    public const BROADCAST = 'broadcast';

    public const AUTOMATION = 'automation';

    public static function all(): array
    {
        return [self::BROADCAST, self::AUTOMATION];
    }
}
