<?php

namespace App\Domains\Pos\Services;

use App\Domains\Booking\Services\BookingScopeValidator;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use Illuminate\Validation\ValidationException;

class PosScopeValidator
{
    public function __construct(private readonly BookingScopeValidator $bookingScope) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        $this->bookingScope->assertTenantModel($model);
    }

    public function findCheckout(string $id): CommerceCheckout
    {
        $checkout = CommerceCheckout::query()
            ->with(['lines', 'appointmentLinks.appointment', 'client', 'location', 'teamMember'])
            ->findOrFail($id);

        $this->assertTenantModel($checkout);

        return $checkout;
    }

    public function findLine(string $checkoutId, string $lineId): CommerceCheckoutLine
    {
        $line = CommerceCheckoutLine::query()
            ->where('checkout_id', $checkoutId)
            ->findOrFail($lineId);

        $this->assertTenantModel($line);

        return $line;
    }

    public function assertEditable(CommerceCheckout $checkout): void
    {
        $this->assertTenantModel($checkout);

        if (CheckoutStatus::isTerminal($checkout->status)) {
            throw ValidationException::withMessages([
                'status' => ['Checkout cannot be modified in its current state.'],
            ]);
        }
    }
}
