<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Models\PaymentAllocation;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Commerce\Contracts\PaymentAllocationContract;
use App\Shared\Commerce\DTO\PaymentAllocationDto;
use App\Shared\Commerce\Enums\PaymentAllocationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(
        private readonly PaymentScopeValidator $scope,
        private readonly PaymentAllocationContract $allocationValidator,
    ) {}

    /**
     * @param  list<array{allocation_type: string, amount_cents: int, appointment_id?: string|null, commerce_deposit_record_id?: string|null, commerce_checkout_id?: string|null, notes?: string|null}>  $allocations
     * @return list<PaymentAllocation>
     */
    public function attach(PaymentTransaction $transaction, array $allocations): array
    {
        $this->scope->assertTenantModel($transaction);

        $dtos = [];
        foreach ($allocations as $row) {
            $dtos[] = new PaymentAllocationDto(
                allocationType: $row['allocation_type'],
                amountCents: $row['amount_cents'],
                targetType: $this->targetTypeFor($row),
                targetId: $this->targetIdFor($row),
            );
        }

        $this->allocationValidator->validateAllocations($transaction->amount_cents, $dtos);

        return DB::transaction(function () use ($transaction, $allocations) {
            $created = [];

            foreach ($allocations as $row) {
                if (! in_array($row['allocation_type'], PaymentAllocationType::all(), true)) {
                    throw ValidationException::withMessages([
                        'allocation_type' => ['Invalid allocation type.'],
                    ]);
                }

                $created[] = PaymentAllocation::query()->create([
                    'tenant_id' => $transaction->tenant_id,
                    'payment_transaction_id' => $transaction->id,
                    'allocation_type' => $row['allocation_type'],
                    'amount_cents' => $row['amount_cents'],
                    'appointment_id' => $row['appointment_id'] ?? null,
                    'commerce_deposit_record_id' => $row['commerce_deposit_record_id'] ?? null,
                    'commerce_checkout_id' => $row['commerce_checkout_id'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $created;
        });
    }

    private function targetTypeFor(array $row): string
    {
        return match ($row['allocation_type']) {
            PaymentAllocationType::DEPOSIT => 'commerce_deposit_record',
            PaymentAllocationType::CHECKOUT_SALE => 'commerce_checkout',
            default => 'payment_target',
        };
    }

    private function targetIdFor(array $row): string
    {
        return $row['commerce_deposit_record_id']
            ?? $row['commerce_checkout_id']
            ?? $row['appointment_id']
            ?? 'unassigned';
    }
}
