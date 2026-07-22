<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\WalletEntryDirection;
use Illuminate\Support\Facades\DB;

/**
 * Point-in-time membership / wallet / loyalty metrics for the overview dashboard.
 *
 * Wallet and loyalty balances are derived from ledger tables (credit minus debit,
 * excluding expired entries) — the same rules as WalletLedgerService and
 * LoyaltyLedgerService.
 */
class MembershipMetricsService
{
    /**
     * @return array<string, int>
     */
    public function snapshot(string $tenantId): array
    {
        return [
            'active_memberships' => (int) DB::table('client_memberships')
                ->where('tenant_id', $tenantId)
                ->where('status', ClientMembershipStatus::ACTIVE)
                ->count(),
            'active_packages' => (int) DB::table('client_packages')
                ->where('tenant_id', $tenantId)
                ->where('status', ClientPackageStatus::ACTIVE)
                ->count(),
            'wallet_liability_cents' => $this->walletLiabilityCents($tenantId),
            'loyalty_points_outstanding' => $this->loyaltyPointsOutstanding($tenantId),
        ];
    }

    private function walletLiabilityCents(string $tenantId): int
    {
        $rows = DB::table('client_wallet_entries')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->selectRaw('client_id, direction, SUM(amount_cents) as total')
            ->groupBy('client_id', 'direction')
            ->get();

        $byClient = [];
        foreach ($rows as $row) {
            $byClient[$row->client_id] ??= ['credit' => 0, 'debit' => 0];
            if ($row->direction === WalletEntryDirection::CREDIT) {
                $byClient[$row->client_id]['credit'] = (int) $row->total;
            } else {
                $byClient[$row->client_id]['debit'] = (int) $row->total;
            }
        }

        $total = 0;
        foreach ($byClient as $balances) {
            $total += max(0, $balances['credit'] - $balances['debit']);
        }

        return $total;
    }

    private function loyaltyPointsOutstanding(string $tenantId): int
    {
        $rows = DB::table('client_loyalty_entries')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->selectRaw('client_id, direction, SUM(points) as total')
            ->groupBy('client_id', 'direction')
            ->get();

        $byClient = [];
        foreach ($rows as $row) {
            $byClient[$row->client_id] ??= ['credit' => 0, 'debit' => 0];
            if ($row->direction === LoyaltyEntryDirection::CREDIT) {
                $byClient[$row->client_id]['credit'] = (int) $row->total;
            } else {
                $byClient[$row->client_id]['debit'] = (int) $row->total;
            }
        }

        $total = 0;
        foreach ($byClient as $balances) {
            $total += max(0, $balances['credit'] - $balances['debit']);
        }

        return $total;
    }
}
