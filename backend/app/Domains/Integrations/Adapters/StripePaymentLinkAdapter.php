<?php

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\ProviderOutboundAdapterContract;
use App\Domains\Integrations\DTO\OutboundProviderDispatchDto;
use App\Domains\Integrations\DTO\ProviderAdapterResultDto;
use App\Domains\Integrations\Enums\ProviderCategory;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Models\ProviderAccount;
use Illuminate\Support\Str;

/**
 * Stripe payment-link adapter — stub transport (no HTTP SDK).
 */
final class StripePaymentLinkAdapter implements ProviderOutboundAdapterContract
{
    public function driver(): string
    {
        return ProviderDriver::STRIPE;
    }

    public function supportsCategory(string $category): bool
    {
        return $category === ProviderCategory::PAYMENT_GATEWAY;
    }

    public function dispatch(ProviderAccount $account, OutboundProviderDispatchDto $dto): ProviderAdapterResultDto
    {
        if (($dto->metadata['simulate_failure'] ?? false) === true) {
            return ProviderAdapterResultDto::failed(
                'Stripe stub payment link rejected (simulated failure).',
                'stripe_stub_failure',
                ['transport' => 'stub', 'driver' => ProviderDriver::STRIPE],
            );
        }

        $reference = 'plink_stub_'.Str::lower(Str::random(12));

        return ProviderAdapterResultDto::delivered(
            providerReference: $reference,
            remoteStatus: 'requires_payment_method',
            simulated: false,
            metadata: [
                'transport' => 'stub',
                'driver' => ProviderDriver::STRIPE,
                'amount_cents' => $dto->payload['amount_cents'] ?? null,
            ],
        );
    }
}
