<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Adapters\GeniusWhatsAppAdapter;
use App\Domains\Integrations\Adapters\MailgunEmailAdapter;
use App\Domains\Integrations\Adapters\StripePaymentLinkAdapter;
use App\Domains\Integrations\Adapters\TwilioSmsAdapter;
use App\Domains\Integrations\Contracts\ProviderOutboundAdapterContract;
use Illuminate\Support\Collection;

final class ProviderAdapterRegistry
{
    /** @var Collection<int, ProviderOutboundAdapterContract> */
    private Collection $adapters;

    public function __construct(
        MailgunEmailAdapter $mailgun,
        TwilioSmsAdapter $twilio,
        GeniusWhatsAppAdapter $genius,
        StripePaymentLinkAdapter $stripe,
    ) {
        $this->adapters = collect([$mailgun, $twilio, $genius, $stripe]);
    }

    public function resolve(string $driver): ?ProviderOutboundAdapterContract
    {
        return $this->adapters->first(fn (ProviderOutboundAdapterContract $adapter) => $adapter->driver() === $driver);
    }

    public function hasLiveAdapter(string $driver): bool
    {
        return $this->resolve($driver) !== null;
    }
}
