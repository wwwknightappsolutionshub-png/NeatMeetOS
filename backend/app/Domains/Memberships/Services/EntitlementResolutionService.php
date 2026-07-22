<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\PackageRedemptionState;
use App\Domains\Memberships\Models\ClientPackage;
use App\Shared\Commerce\Contracts\EntitlementResolutionContract;
use App\Shared\Commerce\DTO\EntitlementRedemptionReferenceDto;
use App\Shared\Commerce\Enums\EntitlementReferenceState;

class EntitlementResolutionService implements EntitlementResolutionContract
{
    public function __construct(private readonly MembershipScopeValidator $scope) {}

    public function resolveForServiceLine(
        string $appointmentServiceLineId,
        ?string $entitlementId,
        ?string $entitlementSource,
    ): EntitlementRedemptionReferenceDto {
        if ($entitlementId === null) {
            return new EntitlementRedemptionReferenceDto(
                entitlementId: null,
                entitlementSource: $entitlementSource,
                state: EntitlementReferenceState::REFERENCED,
                appointmentServiceLineId: $appointmentServiceLineId,
            );
        }

        $package = ClientPackage::query()->find($entitlementId);

        if ($package === null) {
            return new EntitlementRedemptionReferenceDto(
                entitlementId: $entitlementId,
                entitlementSource: $entitlementSource ?? 'package',
                state: EntitlementReferenceState::REFERENCED,
                appointmentServiceLineId: $appointmentServiceLineId,
            );
        }

        try {
            $this->scope->assertTenantModel($package);
        } catch (\Throwable) {
            return new EntitlementRedemptionReferenceDto(
                entitlementId: $entitlementId,
                entitlementSource: $entitlementSource ?? 'package',
                state: EntitlementReferenceState::EXPIRED,
                appointmentServiceLineId: $appointmentServiceLineId,
            );
        }

        $state = $this->resolveState($package, $appointmentServiceLineId);

        return new EntitlementRedemptionReferenceDto(
            entitlementId: $entitlementId,
            entitlementSource: $entitlementSource ?? 'package',
            state: $state,
            appointmentServiceLineId: $appointmentServiceLineId,
        );
    }

    private function resolveState(ClientPackage $package, string $appointmentServiceLineId): string
    {
        $serviceLine = AppointmentServiceLine::query()->find($appointmentServiceLineId);

        if ($serviceLine?->entitlement_state === 'reserved') {
            return EntitlementReferenceState::RESERVED;
        }

        if ($serviceLine?->entitlement_state === 'redeemed') {
            return EntitlementReferenceState::REDEEMED;
        }

        if ($package->status === ClientPackageStatus::DEPLETED || (float) $package->quantity_remaining <= 0) {
            return EntitlementReferenceState::EXPIRED;
        }

        if ($package->status !== ClientPackageStatus::ACTIVE) {
            return EntitlementReferenceState::EXPIRED;
        }

        if ($package->expires_at !== null && $package->expires_at->isPast()) {
            return EntitlementReferenceState::EXPIRED;
        }

        return EntitlementReferenceState::REFERENCED;
    }
}
