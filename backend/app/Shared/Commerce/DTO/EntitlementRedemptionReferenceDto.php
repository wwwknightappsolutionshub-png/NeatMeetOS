<?php

namespace App\Shared\Commerce\DTO;

readonly class EntitlementRedemptionReferenceDto
{
    public function __construct(
        public ?string $entitlementId,
        public ?string $entitlementSource,
        public string $state,
        public string $appointmentServiceLineId,
        public ?string $checkoutLineId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'entitlement_id' => $this->entitlementId,
            'entitlement_source' => $this->entitlementSource,
            'state' => $this->state,
            'appointment_service_line_id' => $this->appointmentServiceLineId,
            'checkout_line_id' => $this->checkoutLineId,
        ];
    }
}
