<?php

namespace App\Domains\Pos\Services;

use App\Domains\Booking\Models\AppointmentServiceLine;
use App\Domains\Memberships\Http\Resources\ClientPackageResource;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Domains\Memberships\Services\WalletLedgerService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use App\Shared\Commerce\Services\CommerceEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutMembershipApplicationService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly PackageEntitlementService $packageEntitlement,
        private readonly WalletLedgerService $walletLedger,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly LoyaltyRedemptionSettingsService $loyaltySettings,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly CommerceEventPublisher $eventPublisher,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function membershipOptions(string $checkoutId): array
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $checkout->load('lines');

        $walletBalance = 0;
        $loyaltyBalance = 0;
        $loyaltyRedeemableValue = 0;
        $loyaltySetting = $this->loyaltySettings->get();

        if ($checkout->client_id !== null) {
            $walletBalance = $this->walletLedger->balanceForClient($checkout->client_id);
            $loyaltyBalance = $this->loyaltyLedger->balanceForClient($checkout->client_id);
            $loyaltyRedeemableValue = $this->loyaltySettings->redeemableValueCents($loyaltyBalance);
        }

        $serviceLines = $checkout->lines
            ->filter(fn (CommerceCheckoutLine $line) => $line->line_type === SaleLineType::APPOINTMENT_SERVICE)
            ->map(function (CommerceCheckoutLine $line) use ($checkout) {
                $bookingServiceId = $line->pricing_snapshot['booking_service_id'] ?? null;
                $eligible = $checkout->client_id !== null
                    ? $this->packageEntitlement->listEligibleForClient($checkout->client_id, $bookingServiceId)
                    : collect();

                $reserved = null;
                if ($line->reference_type === 'appointment_service' && $line->reference_id !== null) {
                    $serviceLine = AppointmentServiceLine::query()->find($line->reference_id);
                    if ($serviceLine?->client_package_redemption_id !== null) {
                        $reserved = [
                            'client_package_id' => $serviceLine->client_package_id,
                            'client_package_redemption_id' => $serviceLine->client_package_redemption_id,
                            'covered_quantity' => $serviceLine->covered_quantity,
                            'covered_amount_cents' => $serviceLine->covered_amount_cents ?? 0,
                        ];
                    }
                }

                return [
                    'line_id' => $line->id,
                    'description' => $line->description,
                    'line_total_cents' => $line->line_total_cents,
                    'booking_service_id' => $bookingServiceId,
                    'applied_package' => $line->client_package_id !== null ? [
                        'client_package_id' => $line->client_package_id,
                        'client_package_redemption_id' => $line->client_package_redemption_id,
                        'covered_quantity' => $line->covered_quantity,
                        'covered_amount_cents' => $line->covered_amount_cents ?? 0,
                    ] : null,
                    'reserved_package' => $reserved,
                    'eligible_packages' => ClientPackageResource::collection($eligible),
                ];
            })
            ->values();

        return [
            'checkout_id' => $checkout->id,
            'client_id' => $checkout->client_id,
            'wallet_balance_cents' => $walletBalance,
            'wallet_credit_applied_cents' => $checkout->wallet_credit_cents ?? 0,
            'loyalty_points_balance' => $loyaltyBalance,
            'loyalty_redeemable_value_cents' => $loyaltyRedeemableValue,
            'loyalty_points_redeemed' => $checkout->loyalty_points_redeemed ?? 0,
            'loyalty_discount_cents' => $checkout->loyalty_discount_cents ?? 0,
            'package_covered_cents' => $checkout->package_covered_cents ?? 0,
            'loyalty_redemption_rule' => [
                'is_enabled' => $loyaltySetting->is_loyalty_redemption_enabled,
                'points_per_block' => $loyaltySetting->points_per_redemption_block,
                'value_cents_per_block' => $loyaltySetting->value_cents_per_block,
            ],
            'service_lines' => $serviceLines,
        ];
    }

    public function applyWallet(string $checkoutId, int $amountCents, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        if ($checkout->client_id === null) {
            throw ValidationException::withMessages(['client_id' => ['Checkout must have a client to apply wallet credit.']]);
        }

        if (($checkout->wallet_credit_cents ?? 0) > 0) {
            throw ValidationException::withMessages(['wallet' => ['Wallet credit is already applied. Remove it first.']]);
        }

        $checkout = $this->totalsRecalculator->recalculate($checkout);
        $balance = $this->walletLedger->balanceForClient($checkout->client_id);
        $creditAmount = min($amountCents, $balance, max(0, $checkout->amount_due_cents));

        if ($creditAmount <= 0) {
            throw ValidationException::withMessages(['amount_cents' => ['No wallet credit can be applied.']]);
        }

        return DB::transaction(function () use ($checkout, $creditAmount, $teamMemberId) {
            $this->walletLedger->redeemForCheckout($checkout->client_id, $checkout->id, $creditAmount, $teamMemberId);

            $checkout->wallet_credit_cents = $creditAmount;
            $checkout->save();

            $this->auditLogger->log('checkout.wallet_applied', $checkout, null, [
                'amount_cents' => $creditAmount,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::WALLET_REDEEMED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: ['amount_cents' => $creditAmount],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function removeWallet(string $checkoutId, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        if (($checkout->wallet_credit_cents ?? 0) <= 0) {
            return $checkout;
        }

        return DB::transaction(function () use ($checkout, $teamMemberId) {
            if ($checkout->client_id !== null) {
                $this->walletLedger->restoreForCheckout(
                    $checkout->client_id,
                    $checkout->id,
                    'Wallet application removed from checkout',
                    $teamMemberId,
                );
            }

            $checkout->wallet_credit_cents = 0;
            $checkout->save();

            $this->auditLogger->log('checkout.wallet_removed', $checkout, null, []);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::WALLET_RESTORED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function applyLoyalty(string $checkoutId, int $points, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        if ($checkout->client_id === null) {
            throw ValidationException::withMessages(['client_id' => ['Checkout must have a client to redeem loyalty points.']]);
        }

        if (($checkout->loyalty_points_redeemed ?? 0) > 0) {
            throw ValidationException::withMessages(['loyalty' => ['Loyalty redemption is already applied. Remove it first.']]);
        }

        $redemption = $this->loyaltySettings->computeRedemptionValue($points);
        $balance = $this->loyaltyLedger->balanceForClient($checkout->client_id);

        if ($redemption['points'] > $balance) {
            throw ValidationException::withMessages(['points' => ['Insufficient loyalty points.']]);
        }

        $checkout = $this->totalsRecalculator->recalculate($checkout);
        $discountCents = min($redemption['value_cents'], max(0, $checkout->amount_due_cents));

        if ($discountCents <= 0) {
            throw ValidationException::withMessages(['points' => ['No loyalty discount can be applied.']]);
        }

        return DB::transaction(function () use ($checkout, $redemption, $discountCents, $teamMemberId) {
            $this->loyaltyLedger->redeemForCheckout(
                $checkout->client_id,
                $checkout->id,
                $redemption['points'],
                $discountCents,
                $teamMemberId,
            );

            $checkout->loyalty_points_redeemed = $redemption['points'];
            $checkout->loyalty_discount_cents = $discountCents;
            $checkout->save();

            $this->auditLogger->log('checkout.loyalty_applied', $checkout, null, [
                'points' => $redemption['points'],
                'discount_cents' => $discountCents,
            ]);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::LOYALTY_REDEEMED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [
                    'points' => $redemption['points'],
                    'discount_cents' => $discountCents,
                ],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function removeLoyalty(string $checkoutId, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        if (($checkout->loyalty_points_redeemed ?? 0) <= 0) {
            return $checkout;
        }

        return DB::transaction(function () use ($checkout, $teamMemberId) {
            if ($checkout->client_id !== null) {
                $this->loyaltyLedger->restoreForCheckout(
                    $checkout->client_id,
                    $checkout->id,
                    'Loyalty redemption removed from checkout',
                    $teamMemberId,
                );
            }

            $checkout->loyalty_points_redeemed = 0;
            $checkout->loyalty_discount_cents = 0;
            $checkout->save();

            $this->auditLogger->log('checkout.loyalty_removed', $checkout, null, []);

            $this->eventPublisher->publish(new CommerceEventDto(
                eventName: CommerceEventName::LOYALTY_RESTORED,
                tenantId: $checkout->tenant_id,
                aggregateType: 'commerce_checkout',
                aggregateId: $checkout->id,
                payload: [],
            ));

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function applyPackageToLine(
        string $checkoutId,
        string $lineId,
        string $clientPackageId,
        ?float $quantity = null,
        ?string $teamMemberId = null,
    ): CommerceCheckout {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);
        $line = $this->scope->findLine($checkoutId, $lineId);

        DB::transaction(function () use ($checkout, $line, $clientPackageId, $quantity, $teamMemberId) {
            $this->packageEntitlement->applyToCheckoutLine($checkout, $line, $clientPackageId, $quantity, $teamMemberId);
        });

        return $this->totalsRecalculator->recalculate($checkout->fresh());
    }

    public function removePackageFromLine(string $checkoutId, string $lineId, ?string $teamMemberId = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);
        $line = $this->scope->findLine($checkoutId, $lineId);

        DB::transaction(function () use ($checkout, $line, $teamMemberId) {
            $this->packageEntitlement->removeFromCheckoutLine($checkout, $line, $teamMemberId);
        });

        return $this->totalsRecalculator->recalculate($checkout->fresh());
    }

    public function linkImportedPackageReservations(CommerceCheckout $checkout): void
    {
        $checkout->load('lines');

        foreach ($checkout->lines as $line) {
            if ($line->line_type !== SaleLineType::APPOINTMENT_SERVICE
                || $line->reference_type !== 'appointment_service'
                || $line->reference_id === null) {
                continue;
            }

            if ($line->client_package_redemption_id !== null) {
                continue;
            }

            $serviceLine = AppointmentServiceLine::query()->find($line->reference_id);

            if ($serviceLine?->client_package_redemption_id === null || $serviceLine->client_package_id === null) {
                continue;
            }

            $this->packageEntitlement->applyToCheckoutLine(
                $checkout,
                $line,
                $serviceLine->client_package_id,
                $serviceLine->covered_quantity !== null ? (float) $serviceLine->covered_quantity : null,
            );
        }
    }

    public function restoreAllApplications(CommerceCheckout $checkout, string $reason, ?string $teamMemberId = null): void
    {
        $checkout->load('lines');

        $this->packageEntitlement->restoreApplications($checkout, $reason, $teamMemberId);

        if (($checkout->wallet_credit_cents ?? 0) > 0 && $checkout->client_id !== null) {
            $this->walletLedger->restoreForCheckout($checkout->client_id, $checkout->id, $reason, $teamMemberId);
            $checkout->wallet_credit_cents = 0;
        }

        if (($checkout->loyalty_points_redeemed ?? 0) > 0 && $checkout->client_id !== null) {
            $this->loyaltyLedger->restoreForCheckout($checkout->client_id, $checkout->id, $reason, $teamMemberId);
            $checkout->loyalty_points_redeemed = 0;
            $checkout->loyalty_discount_cents = 0;
        }

        $checkout->package_covered_cents = 0;
        $checkout->save();
    }
}
