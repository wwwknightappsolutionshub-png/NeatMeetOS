<?php

namespace App\Domains\Marketing\Enums;

final class MarketingWorkflowStepType
{
    public const SEND_MESSAGE = 'send_message';

    public const WAIT = 'wait';

    public const TAG_CLIENT = 'tag_client';

    public const INTERNAL_NOTE = 'internal_note';

    public static function all(): array
    {
        return [self::SEND_MESSAGE, self::WAIT, self::TAG_CLIENT, self::INTERNAL_NOTE];
    }

    /**
     * Step types with full operational execution in Module 10B. Others are stored/deferred.
     */
    public static function operational(): array
    {
        return [self::SEND_MESSAGE, self::WAIT];
    }
}
