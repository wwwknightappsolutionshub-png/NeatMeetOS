<?php

namespace App\Shared\Commerce\DTO;

readonly class DepositContractDto
{
    /**
     * @param  array<string, mixed>|null  $ruleSnapshot
     */
    public function __construct(
        public string $appointmentId,
        public string $bookingDepositStatus,
        public string $lifecycleState,
        public ?int $requiredCents,
        public ?int $collectedCents,
        public ?string $depositRecordId,
        public ?array $ruleSnapshot,
    ) {}

    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'booking_deposit_status' => $this->bookingDepositStatus,
            'lifecycle_state' => $this->lifecycleState,
            'required_cents' => $this->requiredCents,
            'collected_cents' => $this->collectedCents,
            'deposit_record_id' => $this->depositRecordId,
            'rule_snapshot' => $this->ruleSnapshot,
        ];
    }
}
