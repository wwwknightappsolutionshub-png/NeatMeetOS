<?php

namespace App\Domains\Pos\Services;

use App\Domains\Payments\Models\PaymentAllocation;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutVoidService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutMembershipApplicationService $membershipApplication,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function void(string $checkoutId, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $hasPayments = PaymentAllocation::query()
            ->where('commerce_checkout_id', $checkout->id)
            ->exists();

        if ($hasPayments) {
            throw ValidationException::withMessages([
                'checkout' => ['Checkouts with recorded payments cannot be voided in this release.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $teamMemberId) {
            $this->membershipApplication->restoreAllApplications($checkout, 'Checkout voided', $teamMemberId);

            $checkout->status = CheckoutStatus::VOIDED;
            $checkout->voided_at = now();
            $checkout->save();

            $this->auditLogger->log('checkout.voided', $checkout, null, [
                'checkout_number' => $checkout->checkout_number,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::CHECKOUT_VOIDED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'checkout_number' => $checkout->checkout_number,
                ],
            ));

            return $this->scope->findCheckout($checkout->id);
        });
    }
}
