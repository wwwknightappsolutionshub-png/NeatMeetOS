<?php

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTO\ProviderDispatchResultDto;
use App\Domains\Payments\Models\PaymentTransaction;

/**
 * Lightweight contract for Payments to log outbound provider-facing actions.
 */
interface PaymentProviderAttemptContract
{
    public function recordPaymentLink(PaymentTransaction $transaction): ProviderDispatchResultDto;
}
