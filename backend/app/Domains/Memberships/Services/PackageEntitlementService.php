<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\MembershipApplicationType;
use App\Domains\Memberships\Enums\PackageRedemptionState;
use App\Domains\Memberships\Enums\PackageRedemptionType;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientPackageRedemption;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\EntitlementReferenceState;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PackageEntitlementService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function listEligibleForClient(string $clientId, ?string $bookingServiceId = null): Collection
    {
        $this->scope->findClient($clientId);

        $query = ClientPackage::query()
            ->with(['packageProduct.bookingServices'])
            ->where('client_id', $clientId)
            ->where('status', ClientPackageStatus::ACTIVE)
            ->where('quantity_remaining', '>', 0);

        $packages = $query->get()->filter(function (ClientPackage $package) use ($bookingServiceId) {
            if ($package->expires_at !== null && $package->expires_at->isPast()) {
                return false;
            }

            if ($bookingServiceId === null) {
                return true;
            }

            $restrictions = $package->packageProduct?->bookingServices ?? collect();

            if ($restrictions->isEmpty()) {
                return true;
            }

            return $restrictions->contains('id', $bookingServiceId);
        });

        return new Collection($packages->values()->all());
    }

    public function reserveForServiceLine(
        Appointment $appointment,
        AppointmentServiceLine $serviceLine,
        string $clientPackageId,
        float $quantity,
        ?string $teamMemberId = null,
    ): ClientPackageRedemption {
        $this->scope->assertTenantModel($appointment);
        $this->scope->assertTenantModel($serviceLine);

        if ($appointment->client_id === null) {
            throw ValidationException::withMessages(['client_id' => ['Appointment must have a client.']]);
        }

        $package = $this->scope->findClientPackage($clientPackageId);

        if ($package->client_id !== $appointment->client_id) {
            throw ValidationException::withMessages(['client_package_id' => ['Package does not belong to appointment client.']]);
        }

        $this->assertPackageAvailable($package, $quantity);
        $this->assertServiceRestriction($package, $serviceLine->booking_service_id);

        if ($serviceLine->client_package_redemption_id !== null) {
            throw ValidationException::withMessages(['service_line' => ['Service line already has a package reservation.']]);
        }

        return DB::transaction(function () use ($appointment, $serviceLine, $package, $quantity, $teamMemberId) {
            $coveredAmount = $this->coverageAmountForServiceLine($serviceLine, $quantity);

            $package->quantity_remaining = (float) $package->quantity_remaining - $quantity;
            if ((float) $package->quantity_remaining <= 0) {
                $package->quantity_remaining = 0;
            }
            $package->save();

            $redemption = ClientPackageRedemption::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_package_id' => $package->id,
                'client_id' => $package->client_id,
                'booking_service_id' => $serviceLine->booking_service_id,
                'appointment_id' => $appointment->id,
                'appointment_service_line_id' => $serviceLine->id,
                'redemption_type' => PackageRedemptionType::BOOKING_REDEEM,
                'state' => PackageRedemptionState::RESERVED,
                'quantity' => $quantity,
                'reserved_at' => now(),
                'unit_value_cents' => $serviceLine->price_cents,
                'covered_amount_cents' => $coveredAmount,
                'created_by_team_member_id' => $teamMemberId,
            ]);

            $serviceLine->package_entitlement_id = $package->id;
            $serviceLine->entitlement_source = 'package';
            $serviceLine->entitlement_state = EntitlementReferenceState::RESERVED;
            $serviceLine->client_package_id = $package->id;
            $serviceLine->client_package_redemption_id = $redemption->id;
            $serviceLine->covered_quantity = $quantity;
            $serviceLine->covered_amount_cents = $coveredAmount;
            $serviceLine->save();

            $this->auditLogger->log('client_package.reserved', $package, null, [
                'appointment_service_line_id' => $serviceLine->id,
                'quantity' => $quantity,
            ]);

            $this->publishEvent(CommerceEventName::PACKAGE_RESERVED, $package->id, [
                'client_package_id' => $package->id,
                'redemption_id' => $redemption->id,
            ]);

            return $redemption;
        });
    }

    public function releaseReservation(AppointmentServiceLine $serviceLine, ?string $teamMemberId = null): void
    {
        $this->scope->assertTenantModel($serviceLine);

        if ($serviceLine->client_package_redemption_id === null) {
            return;
        }

        $redemption = ClientPackageRedemption::query()->find($serviceLine->client_package_redemption_id);

        if ($redemption === null || $redemption->state !== PackageRedemptionState::RESERVED) {
            throw ValidationException::withMessages(['package' => ['No active reservation to release.']]);
        }

        DB::transaction(function () use ($serviceLine, $redemption, $teamMemberId) {
            $package = $this->scope->findClientPackage($redemption->client_package_id);
            $package->quantity_remaining = (float) $package->quantity_remaining + (float) $redemption->quantity;
            if ($package->status === ClientPackageStatus::DEPLETED) {
                $package->status = ClientPackageStatus::ACTIVE;
            }
            $package->save();

            $redemption->state = PackageRedemptionState::RELEASED;
            $redemption->released_at = now();
            $redemption->save();

            $serviceLine->package_entitlement_id = null;
            $serviceLine->entitlement_source = null;
            $serviceLine->entitlement_state = EntitlementReferenceState::RESTORED;
            $serviceLine->client_package_id = null;
            $serviceLine->client_package_redemption_id = null;
            $serviceLine->covered_quantity = null;
            $serviceLine->covered_amount_cents = 0;
            $serviceLine->save();

            $this->auditLogger->log('client_package.released', $package, null, [
                'redemption_id' => $redemption->id,
            ]);
        });
    }

    public function applyToCheckoutLine(
        CommerceCheckout $checkout,
        CommerceCheckoutLine $line,
        string $clientPackageId,
        ?float $quantity = null,
        ?string $teamMemberId = null,
    ): ClientPackageRedemption {
        $this->scope->assertTenantModel($checkout);
        $this->scope->assertTenantModel($line);

        if ($checkout->client_id === null) {
            throw ValidationException::withMessages(['client_id' => ['Checkout must have a client.']]);
        }

        if ($line->line_type !== SaleLineType::APPOINTMENT_SERVICE) {
            throw ValidationException::withMessages(['line' => ['Package coverage applies to service lines only.']]);
        }

        if ($line->client_package_redemption_id !== null) {
            throw ValidationException::withMessages(['line' => ['Line already has package coverage.']]);
        }

        $qty = $quantity ?? 1.0;
        $appointmentServiceLineId = $line->reference_type === 'appointment_service'
            ? $line->reference_id
            : null;
        $existingRedemption = null;

        if ($appointmentServiceLineId !== null) {
            $serviceLine = AppointmentServiceLine::query()->find($appointmentServiceLineId);
            if ($serviceLine?->client_package_redemption_id !== null) {
                $existingRedemption = ClientPackageRedemption::query()->find($serviceLine->client_package_redemption_id);
                if ($existingRedemption !== null
                    && $existingRedemption->state === PackageRedemptionState::RESERVED
                    && $existingRedemption->client_package_id === $clientPackageId) {
                    return $this->linkReservationToCheckoutLine($checkout, $line, $existingRedemption, $serviceLine);
                }
            }
        }

        $package = $this->scope->findClientPackage($clientPackageId);

        if ($package->client_id !== $checkout->client_id) {
            throw ValidationException::withMessages(['client_package_id' => ['Package does not belong to checkout client.']]);
        }

        $bookingServiceId = $line->pricing_snapshot['booking_service_id'] ?? null;
        $this->assertPackageAvailable($package, $qty);
        $this->assertServiceRestriction($package, $bookingServiceId);

        return DB::transaction(function () use ($checkout, $line, $package, $qty, $teamMemberId, $appointmentServiceLineId) {
            $coveredAmount = min($line->line_total_cents, (int) ($line->unit_price_cents * $qty));

            $package->quantity_remaining = (float) $package->quantity_remaining - $qty;
            if ((float) $package->quantity_remaining <= 0) {
                $package->quantity_remaining = 0;
            }
            $package->save();

            $redemption = ClientPackageRedemption::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_package_id' => $package->id,
                'client_id' => $package->client_id,
                'booking_service_id' => $line->pricing_snapshot['booking_service_id'] ?? null,
                'appointment_id' => $line->pricing_snapshot['appointment_id'] ?? null,
                'appointment_service_line_id' => $appointmentServiceLineId,
                'checkout_id' => $checkout->id,
                'redemption_type' => PackageRedemptionType::POS_REDEEM,
                'state' => PackageRedemptionState::RESERVED,
                'quantity' => $qty,
                'reserved_at' => now(),
                'unit_value_cents' => $line->unit_price_cents,
                'covered_amount_cents' => $coveredAmount,
                'created_by_team_member_id' => $teamMemberId,
            ]);

            Schema::withoutForeignKeyConstraints(function () use ($line, $redemption, $package, $qty, $coveredAmount) {
                $line->membership_application_type = MembershipApplicationType::PACKAGE;
                $line->client_package_id = $package->id;
                $line->client_package_redemption_id = $redemption->id;
                $line->covered_quantity = $qty;
                $line->covered_amount_cents = $coveredAmount;
                $line->save();

                $redemption->checkout_line_id = $line->id;
                $redemption->save();
            });

            $this->auditLogger->log('checkout.package_applied', $checkout, null, [
                'line_id' => $line->id,
                'client_package_id' => $package->id,
                'covered_amount_cents' => $coveredAmount,
            ]);

            return $redemption;
        });
    }

    public function removeFromCheckoutLine(CommerceCheckout $checkout, CommerceCheckoutLine $line, ?string $teamMemberId = null): void
    {
        if ($line->client_package_redemption_id === null) {
            return;
        }

        $redemption = ClientPackageRedemption::query()->find($line->client_package_redemption_id);

        if ($redemption === null || $redemption->state !== PackageRedemptionState::RESERVED) {
            throw ValidationException::withMessages(['package' => ['Package cannot be removed in current state.']]);
        }

        DB::transaction(function () use ($checkout, $line, $redemption) {
            $package = $this->scope->findClientPackage($redemption->client_package_id);
            $package->quantity_remaining = (float) $package->quantity_remaining + (float) $redemption->quantity;
            if ($package->status === ClientPackageStatus::DEPLETED) {
                $package->status = ClientPackageStatus::ACTIVE;
            }
            $package->save();

            $redemption->state = PackageRedemptionState::RELEASED;
            $redemption->released_at = now();
            $redemption->save();

            if ($line->reference_type === 'appointment_service' && $line->reference_id !== null) {
                $serviceLine = AppointmentServiceLine::query()->find($line->reference_id);
                if ($serviceLine !== null) {
                    $serviceLine->entitlement_state = EntitlementReferenceState::RESTORED;
                    $serviceLine->client_package_redemption_id = null;
                    $serviceLine->save();
                }
            }

            $line->membership_application_type = null;
            $line->client_package_id = null;
            $line->client_package_redemption_id = null;
            $line->covered_quantity = null;
            $line->covered_amount_cents = 0;
            $line->save();

            $this->auditLogger->log('checkout.package_removed', $checkout, null, ['line_id' => $line->id]);
        });
    }

    public function finalizeForCheckout(CommerceCheckout $checkout, ?string $teamMemberId = null): void
    {
        foreach ($checkout->lines as $line) {
            if ($line->client_package_redemption_id === null) {
                continue;
            }

            $redemption = ClientPackageRedemption::query()->find($line->client_package_redemption_id);

            if ($redemption === null || $redemption->state !== PackageRedemptionState::RESERVED) {
                continue;
            }

            $redemption->state = PackageRedemptionState::REDEEMED;
            $redemption->redeemed_at = now();
            $redemption->checkout_id = $checkout->id;
            $redemption->checkout_line_id = $line->id;
            $redemption->save();

            if ($line->reference_type === 'appointment_service' && $line->reference_id !== null) {
                AppointmentServiceLine::query()
                    ->where('id', $line->reference_id)
                    ->update(['entitlement_state' => EntitlementReferenceState::REDEEMED]);
            }

            $package = $redemption->clientPackage;
            if ($package !== null && (float) $package->quantity_remaining <= 0) {
                $package->status = ClientPackageStatus::DEPLETED;
                $package->save();
            }

            $this->auditLogger->log('client_package.redeemed', $package, null, [
                'checkout_id' => $checkout->id,
                'line_id' => $line->id,
            ]);

            $this->publishEvent(CommerceEventName::PACKAGE_REDEEMED, $checkout->id, [
                'redemption_id' => $redemption->id,
                'client_package_id' => $redemption->client_package_id,
            ]);
        }
    }

    public function restoreApplications(CommerceCheckout $checkout, string $reason, ?string $teamMemberId = null): void
    {
        foreach ($checkout->lines as $line) {
            $this->restoreForCheckoutLine($checkout, $line, $reason, $teamMemberId);
        }

        $checkout->package_covered_cents = 0;
        $checkout->save();
    }

    public function restoreForCheckoutLine(
        CommerceCheckout $checkout,
        CommerceCheckoutLine $line,
        string $reason,
        ?string $teamMemberId = null,
    ): void {
        if ($line->client_package_redemption_id === null) {
            return;
        }

        $redemption = ClientPackageRedemption::query()->find($line->client_package_redemption_id);

        if ($redemption === null) {
            return;
        }

        if ($redemption->state === PackageRedemptionState::RESERVED) {
            $this->removeFromCheckoutLine($checkout, $line, $teamMemberId);

            return;
        }

        if ($redemption->state !== PackageRedemptionState::REDEEMED) {
            return;
        }

        DB::transaction(function () use ($checkout, $line, $redemption, $reason, $teamMemberId) {
            $package = $this->scope->findClientPackage($redemption->client_package_id);
            $package->quantity_remaining = (float) $package->quantity_remaining + (float) $redemption->quantity;
            $package->status = ClientPackageStatus::ACTIVE;
            $package->save();

            $redemption->state = PackageRedemptionState::RESTORED;
            $redemption->restored_at = now();
            $redemption->restoration_reason = $reason;
            $redemption->save();

            ClientPackageRedemption::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_package_id' => $package->id,
                'client_id' => $package->client_id,
                'checkout_id' => $checkout->id,
                'checkout_line_id' => $line->id,
                'redemption_type' => PackageRedemptionType::REFUND_RESTORE,
                'state' => PackageRedemptionState::RESTORED,
                'quantity' => $redemption->quantity,
                'restored_at' => now(),
                'restoration_reason' => $reason,
                'created_by_team_member_id' => $teamMemberId,
            ]);

            $line->membership_application_type = null;
            $line->client_package_id = null;
            $line->client_package_redemption_id = null;
            $line->covered_quantity = null;
            $line->covered_amount_cents = 0;
            $line->save();

            $this->auditLogger->log('client_package.restored', $package, null, [
                'reason' => $reason,
                'checkout_id' => $checkout->id,
            ]);

            $this->publishEvent(CommerceEventName::PACKAGE_RESTORED, $checkout->id, [
                'redemption_id' => $redemption->id,
            ]);
        });
    }

    private function linkReservationToCheckoutLine(
        CommerceCheckout $checkout,
        CommerceCheckoutLine $line,
        ClientPackageRedemption $redemption,
        AppointmentServiceLine $serviceLine,
    ): ClientPackageRedemption {
        $redemption->checkout_id = $checkout->id;
        $redemption->save();

        Schema::withoutForeignKeyConstraints(function () use ($checkout, $line, $redemption, $serviceLine) {
            $line->membership_application_type = MembershipApplicationType::PACKAGE;
            $line->client_package_id = $redemption->client_package_id;
            $line->client_package_redemption_id = $redemption->id;
            $line->covered_quantity = $redemption->quantity;
            $line->covered_amount_cents = $redemption->covered_amount_cents ?? $line->line_total_cents;
            $line->save();

            $redemption->checkout_line_id = $line->id;
            $redemption->save();

            $serviceLine->entitlement_state = EntitlementReferenceState::RESERVED;
            $serviceLine->save();
        });

        $this->auditLogger->log('checkout.package_applied', $checkout, null, [
            'line_id' => $line->id,
            'from_reservation' => true,
        ]);

        return $redemption;
    }

    private function assertPackageAvailable(ClientPackage $package, float $quantity): void
    {
        if ($package->status !== ClientPackageStatus::ACTIVE) {
            throw ValidationException::withMessages(['package' => ['Package is not active.']]);
        }

        if ($package->expires_at !== null && $package->expires_at->isPast()) {
            throw ValidationException::withMessages(['package' => ['Package has expired.']]);
        }

        if ((float) $package->quantity_remaining < $quantity) {
            throw ValidationException::withMessages(['quantity' => ['Insufficient package balance.']]);
        }
    }

    private function assertServiceRestriction(ClientPackage $package, ?string $bookingServiceId): void
    {
        if ($bookingServiceId === null) {
            return;
        }

        $package->loadMissing('packageProduct.bookingServices');
        $restrictions = $package->packageProduct?->bookingServices ?? collect();

        if ($restrictions->isNotEmpty() && ! $restrictions->contains('id', $bookingServiceId)) {
            throw ValidationException::withMessages(['booking_service_id' => ['Package is not valid for this service.']]);
        }
    }

    private function coverageAmountForServiceLine(AppointmentServiceLine $serviceLine, float $quantity): int
    {
        return (int) min($serviceLine->price_cents, (int) ($serviceLine->price_cents * $quantity));
    }

    private function publishEvent(string $eventName, string $aggregateId, array $payload): void
    {
        $this->eventPublisher->publish(new CommerceEventDto(
            eventName: $eventName,
            tenantId: $this->scope->tenantId(),
            aggregateType: 'client_package',
            aggregateId: $aggregateId,
            payload: $payload,
        ));
    }
}
