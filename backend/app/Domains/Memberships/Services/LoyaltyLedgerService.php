<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class LoyaltyLedgerService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = ClientLoyaltyEntry::query()->with('client')->orderByDesc('effective_at');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        return $query->limit(200)->get();
    }

    public function postEntry(array $data, ?string $teamMemberId = null): ClientLoyaltyEntry
    {
        $client = $this->scope->findClient($data['client_id']);
        $direction = $data['direction'];
        $points = (int) $data['points'];

        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => ['Points must be greater than zero.']]);
        }

        if ($direction === LoyaltyEntryDirection::DEBIT) {
            $balance = $this->balanceForClient($client->id);
            if ($balance < $points) {
                throw ValidationException::withMessages(['points' => ['Insufficient loyalty balance.']]);
            }
        }

        $entry = ClientLoyaltyEntry::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'client_id' => $client->id,
            'entry_type' => $data['entry_type'],
            'direction' => $direction,
            'points' => $points,
            'effective_at' => $data['effective_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'checkout_id' => $data['checkout_id'] ?? null,
            'appointment_id' => $data['appointment_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'restores_entry_id' => $data['restores_entry_id'] ?? null,
            'monetary_value_cents' => $data['monetary_value_cents'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_team_member_id' => $teamMemberId,
        ]);

        $this->auditLogger->log('loyalty.entry_created', $entry, null, $entry->only(['client_id', 'entry_type', 'direction', 'points']));

        return $entry->load('client');
    }

    public function postManualAward(array $data, ?string $teamMemberId = null): ClientLoyaltyEntry
    {
        return $this->postEntry([
            ...$data,
            'entry_type' => LoyaltyEntryType::MANUAL_AWARD,
            'direction' => LoyaltyEntryDirection::CREDIT,
        ], $teamMemberId);
    }

    public function postManualDeduction(array $data, ?string $teamMemberId = null): ClientLoyaltyEntry
    {
        return $this->postEntry([
            ...$data,
            'entry_type' => LoyaltyEntryType::MANUAL_DEDUCTION,
            'direction' => LoyaltyEntryDirection::DEBIT,
        ], $teamMemberId);
    }

    public function balanceForClient(string $clientId): int
    {
        $this->scope->findClient($clientId);

        $entries = ClientLoyaltyEntry::query()
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $credits = $entries->where('direction', LoyaltyEntryDirection::CREDIT)->sum('points');
        $debits = $entries->where('direction', LoyaltyEntryDirection::DEBIT)->sum('points');

        return max(0, (int) $credits - (int) $debits);
    }

    public function redeemForCheckout(
        string $clientId,
        string $checkoutId,
        int $points,
        int $monetaryValueCents,
        ?string $teamMemberId = null,
    ): ClientLoyaltyEntry {
        $entry = $this->postEntry([
            'client_id' => $clientId,
            'entry_type' => LoyaltyEntryType::POS_REDEEM,
            'direction' => LoyaltyEntryDirection::DEBIT,
            'points' => $points,
            'checkout_id' => $checkoutId,
            'monetary_value_cents' => $monetaryValueCents,
            'notes' => 'POS checkout redemption',
        ], $teamMemberId);

        $this->auditLogger->log('loyalty.redeemed', $entry, null, [
            'checkout_id' => $checkoutId,
            'points' => $points,
            'monetary_value_cents' => $monetaryValueCents,
        ]);

        return $entry;
    }

    public function restoreForCheckout(string $clientId, string $checkoutId, string $reason, ?string $teamMemberId = null): ?ClientLoyaltyEntry
    {
        $debit = ClientLoyaltyEntry::query()
            ->where('checkout_id', $checkoutId)
            ->where('entry_type', LoyaltyEntryType::POS_REDEEM)
            ->where('direction', LoyaltyEntryDirection::DEBIT)
            ->first();

        if ($debit === null) {
            return null;
        }

        $existingRestore = ClientLoyaltyEntry::query()
            ->where('restores_entry_id', $debit->id)
            ->exists();

        if ($existingRestore) {
            return null;
        }

        $entry = $this->postEntry([
            'client_id' => $clientId,
            'entry_type' => LoyaltyEntryType::ADJUSTMENT,
            'direction' => LoyaltyEntryDirection::CREDIT,
            'points' => $debit->points,
            'checkout_id' => $checkoutId,
            'restores_entry_id' => $debit->id,
            'monetary_value_cents' => $debit->monetary_value_cents,
            'notes' => $reason,
        ], $teamMemberId);

        $this->auditLogger->log('loyalty.restored', $entry, null, [
            'checkout_id' => $checkoutId,
            'points' => $debit->points,
        ]);

        return $entry;
    }
}
