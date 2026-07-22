<?php

namespace App\Shared\Commerce\Contracts;

use App\Shared\Commerce\DTO\PaymentAllocationDto;

interface PaymentAllocationContract
{
    /**
     * Validate allocation rows sum to transaction amount.
     * Full ledger persistence is implemented in Payments (Module 6).
     *
     * @param  list<PaymentAllocationDto>  $allocations
     */
    public function validateAllocations(int $transactionAmountCents, array $allocations): void;
}
