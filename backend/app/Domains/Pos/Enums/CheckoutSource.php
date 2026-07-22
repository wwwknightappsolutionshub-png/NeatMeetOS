<?php

namespace App\Domains\Pos\Enums;

final class CheckoutSource
{
    public const MANUAL = 'manual';

    public const APPOINTMENT_IMPORT = 'appointment_import';

    public const MIXED = 'mixed';

    public static function all(): array
    {
        return [self::MANUAL, self::APPOINTMENT_IMPORT, self::MIXED];
    }
}
