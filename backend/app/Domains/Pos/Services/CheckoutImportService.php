<?php

namespace App\Domains\Pos\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Pos\Enums\CheckoutSource;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Assemblers\BookingCheckoutImportAssembler;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutAppointment;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutImportService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly BookingCheckoutImportAssembler $importAssembler,
        private readonly CheckoutLineService $lineService,
        private readonly CheckoutMembershipApplicationService $membershipApplication,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function importAppointment(string $checkoutId, string $appointmentId): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->scope->assertTenantModel($appointment);

        $existing = CommerceCheckoutAppointment::query()
            ->where('checkout_id', $checkout->id)
            ->where('appointment_id', $appointment->id)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'appointment_id' => ['This appointment is already linked to this checkout.'],
            ]);
        }

        $import = $this->importAssembler->assemble($appointment);

        if (! $import->checkoutEligible) {
            throw ValidationException::withMessages([
                'appointment_id' => [$import->ineligibilityReason ?? 'Appointment is not eligible for checkout import.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $appointment, $import) {
            $importedSubtotal = 0;

            $linePayloads = [];
            foreach ($import->lines as $index => $lineDto) {
                $importedSubtotal += $lineDto->lineTotalCents;
                $linePayloads[] = [
                    'line_type' => SaleLineType::APPOINTMENT_SERVICE,
                    'description' => $lineDto->description,
                    'quantity' => $lineDto->quantity,
                    'unit_price_cents' => $lineDto->unitPriceCents,
                    'discount_cents' => 0,
                    'line_total_cents' => $lineDto->lineTotalCents,
                    'reference_type' => $lineDto->referenceType,
                    'reference_id' => $lineDto->referenceId,
                    'pricing_snapshot' => $lineDto->pricingSnapshot,
                    'sort_order' => $lineDto->sortOrder ?? ($index + 1),
                ];
            }

            CommerceCheckoutAppointment::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'checkout_id' => $checkout->id,
                'appointment_id' => $appointment->id,
                'role' => CommerceCheckoutAppointment::ROLE_PRIMARY,
                'imported_subtotal_cents' => $importedSubtotal,
            ]);

            if ($checkout->client_id === null && $import->clientId !== null) {
                $checkout->client_id = $import->clientId;
            }

            if ($checkout->location_id === null && $import->locationId !== null) {
                $checkout->location_id = $import->locationId;
            }

            if ($checkout->team_member_id === null && $import->teamMemberId !== null) {
                $checkout->team_member_id = $import->teamMemberId;
            }

            $checkout->source = $checkout->lines()->exists()
                ? CheckoutSource::MIXED
                : CheckoutSource::APPOINTMENT_IMPORT;

            if ($checkout->status === CheckoutStatus::DRAFT) {
                $checkout->status = CheckoutStatus::OPEN;
            }

            $checkout->save();

            foreach ($linePayloads as $payload) {
                CommerceCheckoutLine::query()->create(array_merge([
                    'tenant_id' => $checkout->tenant_id,
                    'checkout_id' => $checkout->id,
                ], $payload));
            }

            $checkout = $this->scope->findCheckout($checkout->id);
            $this->membershipApplication->linkImportedPackageReservations($checkout);
            $checkout = $this->totalsRecalculator->recalculate($checkout);

            $this->auditLogger->log('checkout.appointment_imported', $checkout, null, [
                'appointment_id' => $appointment->id,
                'booking_reference' => $import->bookingReference,
            ]);

            return $this->scope->findCheckout($checkout->id);
        });
    }
}
