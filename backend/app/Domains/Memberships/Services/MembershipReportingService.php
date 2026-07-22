<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\MembershipBillingFrequency;
use App\Domains\Memberships\Enums\WalletEntryDirection;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientWalletEntry;

class MembershipReportingService
{
    public function summary(): array
    {
        $activeSubscriptions = ClientMembership::query()
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->count();

        $mrrCents = ClientMembership::query()
            ->with('membershipPlan')
            ->whereIn('status', [ClientMembershipStatus::ACTIVE, ClientMembershipStatus::TRIALING])
            ->get()
            ->sum(fn (ClientMembership $m) => $this->normalizeToMonthlyCents(
                $m->price_cents_snapshot,
                $m->membershipPlan?->billing_frequency,
            ));

        $walletLiabilityCents = $this->totalWalletLiability();
        $outstandingPackages = ClientPackage::query()
            ->where('status', ClientPackageStatus::ACTIVE)
            ->where('quantity_remaining', '>', 0)
            ->count();

        $loyaltyIssued = ClientLoyaltyEntry::query()
            ->where('direction', LoyaltyEntryDirection::CREDIT)
            ->sum('points');

        return [
            'active_subscriptions_count' => $activeSubscriptions,
            'mrr_estimate_cents' => (int) $mrrCents,
            'wallet_liability_cents' => $walletLiabilityCents,
            'outstanding_package_balances_count' => $outstandingPackages,
            'loyalty_points_issued_total' => (int) $loyaltyIssued,
        ];
    }

    private function normalizeToMonthlyCents(int $priceCents, ?string $frequency): int
    {
        return match ($frequency) {
            MembershipBillingFrequency::WEEKLY => (int) round($priceCents * 52 / 12),
            MembershipBillingFrequency::QUARTERLY => (int) round($priceCents / 3),
            MembershipBillingFrequency::YEARLY => (int) round($priceCents / 12),
            default => $priceCents,
        };
    }

    private function totalWalletLiability(): int
    {
        $entries = ClientWalletEntry::query()
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $credits = $entries->where('direction', WalletEntryDirection::CREDIT)->sum('amount_cents');
        $debits = $entries->where('direction', WalletEntryDirection::DEBIT)->sum('amount_cents');

        return max(0, (int) $credits - (int) $debits);
    }
}
