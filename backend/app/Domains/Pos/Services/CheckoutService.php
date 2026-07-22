<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Enums\CheckoutSource;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly CheckoutNumberGenerator $numberGenerator,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
        private readonly CommerceEventPublisher $eventPublisher,
    ) {}

    public function list(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        $query = CommerceCheckout::query()
            ->with(['client', 'location', 'teamMember'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): CommerceCheckout
    {
        return $this->scope->findCheckout($id);
    }

    public function createDraft(array $data, ?string $teamMemberId = null): CommerceCheckout
    {
        if (empty($data['location_id'])) {
            throw ValidationException::withMessages([
                'location_id' => ['Location is required to open a checkout.'],
            ]);
        }

        return DB::transaction(function () use ($data, $teamMemberId) {
            $checkout = CommerceCheckout::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'checkout_number' => $this->numberGenerator->next($this->scope->tenantId()),
                'client_id' => $data['client_id'] ?? null,
                'location_id' => $data['location_id'],
                'team_member_id' => $data['team_member_id'] ?? $teamMemberId,
                'status' => CheckoutStatus::DRAFT,
                'currency' => $data['currency'] ?? 'GBP',
                'source' => CheckoutSource::MANUAL,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->log('checkout.created', $checkout, null, $checkout->only([
                'checkout_number', 'status', 'location_id', 'client_id',
            ]));

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::CHECKOUT_CREATED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'checkout_number' => $checkout->checkout_number,
                    'status' => $checkout->status,
                ],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function update(string $id, array $data, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($id);
        $this->scope->assertEditable($checkout);

        $old = $checkout->only(['client_id', 'location_id', 'team_member_id', 'notes', 'status']);

        if (isset($data['status']) && $data['status'] === CheckoutStatus::OPEN) {
            $checkout->status = CheckoutStatus::OPEN;
        }

        foreach (['client_id', 'location_id', 'team_member_id', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $checkout->{$field} = $data[$field];
            }
        }

        $checkout->save();

        $this->auditLogger->log('checkout.updated', $checkout, $old, $checkout->only([
            'client_id', 'location_id', 'team_member_id', 'notes', 'status',
        ]));

        return $this->totalsRecalculator->recalculate($checkout);
    }
}
