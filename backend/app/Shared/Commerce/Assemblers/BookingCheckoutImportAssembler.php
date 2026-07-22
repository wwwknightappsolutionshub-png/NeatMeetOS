<?php

namespace App\Shared\Commerce\Assemblers;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Contracts\DepositSettlementContract;
use App\Shared\Commerce\Contracts\EntitlementResolutionContract;
use App\Shared\Commerce\DTO\AppointmentCheckoutImportDto;
use App\Shared\Commerce\DTO\CheckoutLineDto;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Services\AppointmentCheckoutEligibilityValidator;

class BookingCheckoutImportAssembler
{
    public function __construct(
        private readonly AppointmentCheckoutEligibilityValidator $eligibilityValidator,
        private readonly DepositSettlementContract $depositSettlement,
        private readonly EntitlementResolutionContract $entitlementResolution,
    ) {}

    public function assemble(Appointment $appointment): AppointmentCheckoutImportDto
    {
        $appointment->loadMissing(['serviceLines', 'client', 'location', 'teamMember']);

        $eligibility = $this->eligibilityValidator->validate($appointment);

        $lines = [];
        $entitlementReferences = [];

        foreach ($appointment->serviceLines as $serviceLine) {
            $unitPrice = $serviceLine->price_cents ?? 0;

            $lines[] = new CheckoutLineDto(
                lineType: SaleLineType::APPOINTMENT_SERVICE,
                description: $serviceLine->service_name,
                quantity: 1,
                unitPriceCents: $unitPrice,
                lineTotalCents: $unitPrice,
                referenceType: 'appointment_service',
                referenceId: $serviceLine->id,
                pricingSnapshot: [
                    'booking_service_id' => $serviceLine->booking_service_id,
                    'appointment_id' => $appointment->id,
                    'service_name' => $serviceLine->service_name,
                    'duration_minutes' => $serviceLine->duration_minutes,
                    'price_cents' => $serviceLine->price_cents,
                ],
                sortOrder: $serviceLine->sort_order,
            );

            if ($serviceLine->package_entitlement_id !== null || $serviceLine->entitlement_source !== null) {
                $entitlementReferences[] = $this->entitlementResolution->resolveForServiceLine(
                    $serviceLine->id,
                    $serviceLine->package_entitlement_id,
                    $serviceLine->entitlement_source,
                );
            }
        }

        $deposit = $this->depositSettlement->resolveForAppointment($appointment->id);

        return new AppointmentCheckoutImportDto(
            appointmentId: $appointment->id,
            clientId: $appointment->client_id,
            locationId: $appointment->location_id,
            teamMemberId: $appointment->team_member_id,
            bookingReference: $appointment->booking_reference,
            appointmentStatus: $appointment->status,
            bookingSource: $appointment->booking_source,
            lines: $lines,
            deposit: $deposit,
            entitlementReferences: $entitlementReferences,
            checkoutEligible: $eligibility['eligible'],
            ineligibilityReason: $eligibility['reason'] ?? null,
        );
    }
}
