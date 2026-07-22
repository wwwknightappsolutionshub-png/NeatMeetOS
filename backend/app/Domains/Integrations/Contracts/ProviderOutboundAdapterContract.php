<?php

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderAdapterResultDto;
use App\Domains\Integrations\Models\ProviderAccount;

/**
 * Driver-specific outbound adapter (Module 13B).
 *
 * Implementations are stub/live-placeholder capable: they validate credentials and
 * return structured results without requiring production SDK wiring.
 */
interface ProviderOutboundAdapterContract
{
    public function driver(): string;

    public function supportsCategory(string $category): bool;

    public function dispatch(ProviderAccount $account, OutboundProviderDispatchDto $dto): ProviderAdapterResultDto;
}
