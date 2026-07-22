<?php

namespace App\Domains\Payments\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class PaymentScopeValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $bookingScope,
    ) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        $this->bookingScope->assertTenantModel($model);
    }

    public function findAppointment(string $id): Appointment
    {
        $appointment = Appointment::query()->with(['client', 'location', 'serviceLines'])->findOrFail($id);
        $this->assertTenantModel($appointment);

        return $appointment;
    }

    public function findTransaction(string $id): PaymentTransaction
    {
        $transaction = PaymentTransaction::query()->findOrFail($id);
        $this->assertTenantModel($transaction);

        return $transaction;
    }
}
