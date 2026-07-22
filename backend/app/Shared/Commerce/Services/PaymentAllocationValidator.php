<?php

namespace App\Shared\Commerce\Services;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\DTO\PaymentAllocationDto;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use Illuminate\Validation\ValidationException;

class PaymentAllocationValidator implements \App\Shared\Commerce\Contracts\PaymentAllocationContract
{
    /**
     * @param  list<PaymentAllocationDto>  $allocations
     */
    public function validateAllocations(int $transactionAmountCents, array $allocations): void
    {
        if ($transactionAmountCents < 0) {
            throw ValidationException::withMessages([
                'transaction' => ['Transaction amount must be non-negative.'],
            ]);
        }

        $sum = 0;

        foreach ($allocations as $allocation) {
            if (! in_array($allocation->allocationType, PaymentAllocationType::all(), true)) {
                throw ValidationException::withMessages([
                    'allocation_type' => ['Invalid payment allocation type.'],
                ]);
            }

            $sum += $allocation->amountCents;
        }

        if ($sum !== $transactionAmountCents) {
            throw ValidationException::withMessages([
                'allocations' => ['Allocation amounts must sum to transaction amount.'],
            ]);
        }
    }
}
