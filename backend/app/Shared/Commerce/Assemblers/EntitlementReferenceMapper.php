<?php

namespace App\Shared\Commerce\Assemblers;

use App\Shared\Commerce\Contracts\EntitlementResolutionContract;
use App\Shared\Commerce\DTO\EntitlementRedemptionReferenceDto;
use App\Shared\Commerce\Enums\EntitlementReferenceState;

class EntitlementReferenceMapper implements EntitlementResolutionContract
{
    public function resolveForServiceLine(
        string $appointmentServiceLineId,
        ?string $entitlementId,
        ?string $entitlementSource,
    ): EntitlementRedemptionReferenceDto {
        $state = $entitlementId !== null
            ? EntitlementReferenceState::REFERENCED
            : EntitlementReferenceState::REFERENCED;

        return new EntitlementRedemptionReferenceDto(
            entitlementId: $entitlementId,
            entitlementSource: $entitlementSource,
            state: $state,
            appointmentServiceLineId: $appointmentServiceLineId,
        );
    }
}
