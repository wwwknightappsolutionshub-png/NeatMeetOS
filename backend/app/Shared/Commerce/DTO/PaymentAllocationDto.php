<?php

namespace App\Shared\Commerce\DTO;

readonly class PaymentAllocationDto
{
    public function __construct(
        public string $allocationType,
        public int $amountCents,
        public string $targetType,
        public string $targetId,
        public ?string $paymentTransactionId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'allocation_type' => $this->allocationType,
            'amount_cents' => $this->amountCents,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'payment_transaction_id' => $this->paymentTransactionId,
        ];
    }
}
