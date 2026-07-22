<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Memberships\Http\Resources\ClientPackageResource;
use App\Domains\Memberships\Models\ClientPackageRedemption;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookingMembershipService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly PackageEntitlementService $packageEntitlement,
    ) {}

    public function listEligiblePackages(string $appointmentId, ?string $bookingServiceId = null): Collection
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->scope->assertTenantModel($appointment);

        if ($appointment->client_id === null) {
            return collect();
        }

        return $this->packageEntitlement->listEligibleForClient(
            $appointment->client_id,
            $bookingServiceId,
        );
    }

    public function reservePackage(
        string $appointmentId,
        string $serviceLineId,
        string $clientPackageId,
        ?float $quantity = null,
        ?string $teamMemberId = null,
    ): ClientPackageRedemption {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->scope->assertTenantModel($appointment);

        $serviceLine = AppointmentServiceLine::query()
            ->where('appointment_id', $appointment->id)
            ->findOrFail($serviceLineId);
        $this->scope->assertTenantModel($serviceLine);

        $qty = $quantity ?? 1.0;

        return $this->packageEntitlement->reserveForServiceLine(
            $appointment,
            $serviceLine,
            $clientPackageId,
            $qty,
            $teamMemberId,
        );
    }

    public function releasePackage(
        string $appointmentId,
        string $serviceLineId,
        ?string $teamMemberId = null,
    ): void {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->scope->assertTenantModel($appointment);

        $serviceLine = AppointmentServiceLine::query()
            ->where('appointment_id', $appointment->id)
            ->findOrFail($serviceLineId);
        $this->scope->assertTenantModel($serviceLine);

        $this->packageEntitlement->releaseReservation($serviceLine, $teamMemberId);
    }

    public function appointmentPackageSummary(string $appointmentId): array
    {
        $appointment = Appointment::query()
            ->with(['serviceLines.bookableService'])
            ->findOrFail($appointmentId);
        $this->scope->assertTenantModel($appointment);

        $eligible = $appointment->client_id !== null
            ? $this->packageEntitlement->listEligibleForClient($appointment->client_id)
            : collect();

        return [
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'eligible_packages' => ClientPackageResource::collection($eligible),
            'service_lines' => $appointment->serviceLines->map(fn (AppointmentServiceLine $line) => [
                'id' => $line->id,
                'booking_service_id' => $line->booking_service_id,
                'service_name' => $line->bookableService?->name ?? $line->service_name,
                'price_cents' => $line->price_cents,
                'entitlement_state' => $line->entitlement_state,
                'client_package_id' => $line->client_package_id,
                'client_package_redemption_id' => $line->client_package_redemption_id,
                'covered_quantity' => $line->covered_quantity,
                'covered_amount_cents' => $line->covered_amount_cents ?? 0,
            ]),
        ];
    }
}
