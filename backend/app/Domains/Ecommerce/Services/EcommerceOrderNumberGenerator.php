<?php

namespace App\Domains\Ecommerce\Services;

use App\Domains\Ecommerce\Models\EcommerceOrder;

class EcommerceOrderNumberGenerator
{
    public function next(string $tenantId): string
    {
        $count = EcommerceOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        return 'ECO-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
