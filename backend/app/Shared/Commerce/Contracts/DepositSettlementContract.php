<?php

namespace App\Shared\Commerce\Contracts;

use App\Shared\Commerce\DTO\DepositContractDto;

interface DepositSettlementContract
{
    /**
     * Map booking deposit snapshot to cross-module deposit contract.
     * Collection and refund are implemented in Payments (Module 6).
     */
    public function resolveForAppointment(string $appointmentId): DepositContractDto;
}
