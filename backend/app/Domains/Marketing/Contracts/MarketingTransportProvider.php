<?php

namespace App\Domains\Marketing\Contracts;

use App\Domains\Marketing\Models\MarketingMessage;

interface MarketingTransportProvider
{
    /**
     * Dispatch a rendered marketing message via an external provider.
     * Module 13 (Integrations) will implement real providers.
     */
    public function dispatch(MarketingMessage $message): array;
}
