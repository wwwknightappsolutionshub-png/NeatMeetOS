<?php

namespace App\Domains\Memberships\Enums;

final class WalletEntryDirection
{
    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public static function all(): array
    {
        return [self::CREDIT, self::DEBIT];
    }
}
