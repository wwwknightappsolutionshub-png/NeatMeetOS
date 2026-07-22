<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\WalletEntryDirection;
use App\Domains\Memberships\Enums\WalletEntryType;
use App\Domains\Memberships\Models\ClientWalletEntry;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WalletLedgerService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = ClientWalletEntry::query()->with('client')->orderByDesc('balance_effective_at');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        return $query->limit(200)->get();
    }

    public function postEntry(array $data, ?string $teamMemberId = null): ClientWalletEntry
    {
        $client = $this->scope->findClient($data['client_id']);
        $direction = $data['direction'];
        $amountCents = (int) $data['amount_cents'];

        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount_cents' => ['Amount must be greater than zero.']]);
        }

        if ($direction === WalletEntryDirection::DEBIT) {
            $balance = $this->balanceForClient($client->id);
            if ($balance < $amountCents) {
                throw ValidationException::withMessages(['amount_cents' => ['Insufficient wallet balance.']]);
            }
        }

        $entry = ClientWalletEntry::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'client_id' => $client->id,
            'entry_type' => $data['entry_type'],
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'balance_effective_at' => $data['balance_effective_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'checkout_id' => $data['checkout_id'] ?? null,
            'appointment_id' => $data['appointment_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'restores_entry_id' => $data['restores_entry_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_team_member_id' => $teamMemberId,
        ]);

        $this->auditLogger->log('wallet.entry_created', $entry, null, $entry->only(['client_id', 'entry_type', 'direction', 'amount_cents']));

        return $entry->load('client');
    }

    public function postManualCredit(array $data, ?string $teamMemberId = null): ClientWalletEntry
    {
        return $this->postEntry([
            ...$data,
            'entry_type' => WalletEntryType::MANUAL_CREDIT,
            'direction' => WalletEntryDirection::CREDIT,
        ], $teamMemberId);
    }

    public function postManualDebit(array $data, ?string $teamMemberId = null): ClientWalletEntry
    {
        return $this->postEntry([
            ...$data,
            'entry_type' => WalletEntryType::MANUAL_DEBIT,
            'direction' => WalletEntryDirection::DEBIT,
        ], $teamMemberId);
    }

    public function balanceForClient(string $clientId): int
    {
        $this->scope->findClient($clientId);

        $entries = ClientWalletEntry::query()
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $credits = $entries->where('direction', WalletEntryDirection::CREDIT)->sum('amount_cents');
        $debits = $entries->where('direction', WalletEntryDirection::DEBIT)->sum('amount_cents');

        return max(0, (int) $credits - (int) $debits);
    }

    public function redeemForCheckout(string $clientId, string $checkoutId, int $amountCents, ?string $teamMemberId = null): ClientWalletEntry
    {
        $entry = $this->postEntry([
            'client_id' => $clientId,
            'entry_type' => WalletEntryType::POS_REDEMPTION,
            'direction' => WalletEntryDirection::DEBIT,
            'amount_cents' => $amountCents,
            'checkout_id' => $checkoutId,
            'notes' => 'POS checkout redemption',
        ], $teamMemberId);

        $this->auditLogger->log('wallet.redeemed', $entry, null, [
            'checkout_id' => $checkoutId,
            'amount_cents' => $amountCents,
        ]);

        return $entry;
    }

    public function restoreForCheckout(string $clientId, string $checkoutId, string $reason, ?string $teamMemberId = null): ?ClientWalletEntry
    {
        $debit = ClientWalletEntry::query()
            ->where('checkout_id', $checkoutId)
            ->where('entry_type', WalletEntryType::POS_REDEMPTION)
            ->where('direction', WalletEntryDirection::DEBIT)
            ->whereNull('restores_entry_id')
            ->first();

        if ($debit === null) {
            return null;
        }

        $existingRestore = ClientWalletEntry::query()
            ->where('restores_entry_id', $debit->id)
            ->exists();

        if ($existingRestore) {
            return null;
        }

        $entry = $this->postEntry([
            'client_id' => $clientId,
            'entry_type' => WalletEntryType::REFUND_CREDIT,
            'direction' => WalletEntryDirection::CREDIT,
            'amount_cents' => $debit->amount_cents,
            'checkout_id' => $checkoutId,
            'restores_entry_id' => $debit->id,
            'notes' => $reason,
        ], $teamMemberId);

        $this->auditLogger->log('wallet.restored', $entry, null, [
            'checkout_id' => $checkoutId,
            'amount_cents' => $debit->amount_cents,
        ]);

        return $entry;
    }
}
