<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Models\CommerceReceipt;

class ReceiptNumberGenerator
{
    public function next(string $tenantId): string
    {
        $count = CommerceReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        return 'NM-RCP'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
