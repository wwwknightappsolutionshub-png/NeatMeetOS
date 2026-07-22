<?php

namespace App\Domains\Pos\Services;

use Illuminate\Support\Str;

class GiftCardCodeGenerator
{
    public function generate(string $tenantId): string
    {
        return 'NM-GC-'.strtoupper(Str::random(8));
    }
}
