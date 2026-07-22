<?php

namespace App\Domains\Marketing\Enums;

final class MarketingCampaignStatus
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::DRAFT, self::ACTIVE, self::PAUSED, self::ARCHIVED];
    }
}
