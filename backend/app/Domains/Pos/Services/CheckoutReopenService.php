<?php

namespace App\Domains\Pos\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Payments\Models\PaymentRefund;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutReopenService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutMembershipApplicationService $membershipApplication,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function reopen(string $checkoutId, array $data, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->assertReopenEligible($checkout);

        return DB::transaction(function () use ($checkout, $data, $teamMemberId) {
            $this->membershipApplication->restoreAllApplications(
                $checkout,
                'Checkout reopened: '.($data['reason'] ?? 'reopen'),
                $teamMemberId,
            );

            $checkout->status = CheckoutStatus::OPEN;
            $checkout->reopened_at = now();
            $checkout->reopened_by_team_member_id = $teamMemberId;
            $checkout->reopen_reason = $data['reason'] ?? null;
            $checkout->save();

            $this->unsettleLinkedAppointments($checkout);

            $this->auditLogger->log('checkout.reopened', $checkout, null, [
                'reason' => $checkout->reopen_reason,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::CHECKOUT_REOPENED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'action' => 'reopened',
                    'checkout_number' => $checkout->checkout_number,
                ],
            ));

            return $this->scope->findCheckout($checkout->id);
        });
    }

    private function assertReopenEligible(CommerceCheckout $checkout): void
    {
        if ($checkout->status !== CheckoutStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'status' => ['Only completed checkouts can be reopened.'],
            ]);
        }

        if ($checkout->reopened_at !== null) {
            throw ValidationException::withMessages([
                'checkout' => ['This checkout has already been reopened once.'],
            ]);
        }

        if ((int) $checkout->refunded_total_cents > 0) {
            throw ValidationException::withMessages([
                'checkout' => ['Checkouts with refunds cannot be reopened.'],
            ]);
        }

        $hasRefunds = PaymentRefund::query()
            ->where('commerce_checkout_id', $checkout->id)
            ->where('status', PaymentRefund::STATUS_SUCCEEDED)
            ->exists();

        if ($hasRefunds) {
            throw ValidationException::withMessages([
                'checkout' => ['Checkouts with refund records cannot be reopened.'],
            ]);
        }
    }

    private function unsettleLinkedAppointments(CommerceCheckout $checkout): void
    {
        $checkout->loadMissing('appointmentLinks');

        foreach ($checkout->appointmentLinks as $link) {
            Appointment::query()
                ->where('id', $link->appointment_id)
                ->update(['billing_settlement_status' => BillingSettlementStatus::UNSETTLED]);
        }
    }
}
