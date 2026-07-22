<?php

namespace App\Domains\Pos\Services;

use App\Shared\Commerce\Models\CommerceCheckout;

class CheckoutNumberGenerator
{
    public function next(string $tenantId): string
    {
        $count = CommerceCheckout::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        return 'NM-CHK'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
