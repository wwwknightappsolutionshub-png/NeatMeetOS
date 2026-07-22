<?php

namespace App\Shared\Commerce\Contracts;

use App\Shared\Commerce\DTO\EntitlementRedemptionReferenceDto;

interface EntitlementResolutionContract
{
    /**
     * Resolve entitlement reference for an appointment service line.
     * Full reservation/redemption is implemented in Memberships (Module 9).
     */
    public function resolveForServiceLine(
        string $appointmentServiceLineId,
        ?string $entitlementId,
        ?string $entitlementSource,
    ): EntitlementRedemptionReferenceDto;
}
