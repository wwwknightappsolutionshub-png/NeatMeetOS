<?php

namespace App\Shared\Commerce\Services;

use App\Domains\Booking\Models\Appointment;
use App\Shared\Commerce\Assemblers\BookingCheckoutImportAssembler;
use App\Shared\Commerce\Contracts\CheckoutImportFromBookingContract;
use App\Shared\Commerce\DTO\AppointmentCheckoutImportDto;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\DepositLifecycleState;
use App\Shared\Tenancy\TenantContext;

class BookingCommerceImportService implements CheckoutImportFromBookingContract
{
    public function __construct(
        private readonly BookingCheckoutImportAssembler $assembler,
        private readonly CommerceEventPublisher $eventPublisher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function import(Appointment $appointment): AppointmentCheckoutImportDto
    {
        $dto = $this->assembler->assemble($appointment);

        if ($dto->deposit !== null
            && $dto->deposit->lifecycleState === DepositLifecycleState::REQUIRED
            && ($dto->deposit->requiredCents ?? 0) > 0) {
            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::DEPOSIT_REQUIRED,
                tenantId: $this->tenantContext->id() ?? $appointment->tenant_id,
                aggregateType: 'appointment',
                aggregateId: $appointment->id,
                payload: $dto->deposit->toArray(),
            ));
        }

        return $dto;
    }
}
