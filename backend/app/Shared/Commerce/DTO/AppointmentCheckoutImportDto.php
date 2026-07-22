<?php

namespace App\Shared\Commerce\DTO;

readonly class AppointmentCheckoutImportDto
{
    /**
     * @param  list<CheckoutLineDto>  $lines
     * @param  list<EntitlementRedemptionReferenceDto>  $entitlementReferences
     */
    public function __construct(
        public string $appointmentId,
        public string $clientId,
        public string $locationId,
        public ?string $teamMemberId,
        public ?string $bookingReference,
        public string $appointmentStatus,
        public string $bookingSource,
        public array $lines,
        public ?DepositContractDto $deposit,
        public array $entitlementReferences,
        public bool $checkoutEligible,
        public ?string $ineligibilityReason = null,
    ) {}

    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'client_id' => $this->clientId,
            'location_id' => $this->locationId,
            'team_member_id' => $this->teamMemberId,
            'booking_reference' => $this->bookingReference,
            'appointment_status' => $this->appointmentStatus,
            'booking_source' => $this->bookingSource,
            'lines' => array_map(fn (CheckoutLineDto $l) => $l->toArray(), $this->lines),
            'deposit' => $this->deposit?->toArray(),
            'entitlement_references' => array_map(
                fn (EntitlementRedemptionReferenceDto $r) => $r->toArray(),
                $this->entitlementReferences,
            ),
            'checkout_eligible' => $this->checkoutEligible,
            'ineligibility_reason' => $this->ineligibilityReason,
        ];
    }
}
