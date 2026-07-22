<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Enums\GiftCardStatus;
use App\Domains\Pos\Enums\GiftCardTransactionType;
use App\Domains\Pos\Models\GiftCardTransaction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use App\Shared\Commerce\Models\CommerceCheckoutLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GiftCardRedemptionService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly GiftCardService $giftCardService,
        private readonly CheckoutTotalsRecalculator $totalsRecalculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function apply(string $checkoutId, string $code, ?int $amountCents = null): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        $card = $this->giftCardService->findByCodeOrFail($code);

        if ($card->status !== GiftCardStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'code' => ['Gift card is not active.'],
            ]);
        }

        if ($card->current_balance_cents <= 0) {
            throw ValidationException::withMessages([
                'code' => ['Gift card has no remaining balance.'],
            ]);
        }

        if ($this->alreadyApplied($checkout, $card->id)) {
            throw ValidationException::withMessages([
                'code' => ['This gift card is already applied to the checkout.'],
            ]);
        }

        $checkout = $this->totalsRecalculator->recalculate($checkout);
        $creditAmount = min(
            $card->current_balance_cents,
            $amountCents ?? $checkout->amount_due_cents,
            max(0, $checkout->total_cents),
        );

        if ($creditAmount <= 0) {
            throw ValidationException::withMessages([
                'code' => ['No amount due to apply gift card against.'],
            ]);
        }

        return DB::transaction(function () use ($checkout, $card, $creditAmount) {
            CommerceCheckoutLine::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'checkout_id' => $checkout->id,
                'line_type' => SaleLineType::GIFT_CARD_REDEMPTION,
                'description' => 'Gift card '.$card->code,
                'quantity' => 1,
                'unit_price_cents' => $creditAmount,
                'discount_cents' => 0,
                'line_total_cents' => $creditAmount,
                'reference_type' => 'gift_card',
                'reference_id' => $card->id,
                'pricing_snapshot' => [
                    'gift_card_id' => $card->id,
                    'gift_card_code' => $card->code,
                    'pending_redemption_cents' => $creditAmount,
                ],
                'sort_order' => (int) CommerceCheckoutLine::query()
                    ->where('checkout_id', $checkout->id)
                    ->max('sort_order') + 1,
            ]);

            $this->auditLogger->log('checkout.gift_card_applied', $checkout, null, [
                'gift_card_id' => $card->id,
                'amount_cents' => $creditAmount,
            ]);

            return $this->totalsRecalculator->recalculate($checkout);
        });
    }

    public function finalizeForCheckout(CommerceCheckout $checkout, ?string $teamMemberId = null): void
    {
        foreach ($checkout->lines as $line) {
            if ($line->line_type !== SaleLineType::GIFT_CARD_REDEMPTION || $line->reference_id === null) {
                continue;
            }

            $card = \App\Domains\Pos\Models\GiftCard::query()->find($line->reference_id);

            if ($card === null) {
                continue;
            }

            $amount = $line->line_total_cents;

            if ($amount <= 0 || $card->current_balance_cents < $amount) {
                throw ValidationException::withMessages([
                    'gift_card' => ['Gift card balance insufficient at completion.'],
                ]);
            }

            $card->current_balance_cents -= $amount;
            $card->status = $card->current_balance_cents === 0
                ? GiftCardStatus::REDEEMED
                : GiftCardStatus::ACTIVE;
            $card->save();

            GiftCardTransaction::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransactionType::REDEEM,
                'amount_cents' => $amount,
                'commerce_checkout_id' => $checkout->id,
                'created_by_team_member_id' => $teamMemberId,
            ]);
        }
    }

    public function removePending(string $checkoutId): CommerceCheckout
    {
        $checkout = $this->scope->findCheckout($checkoutId);
        $this->scope->assertEditable($checkout);

        CommerceCheckoutLine::query()
            ->where('checkout_id', $checkout->id)
            ->where('line_type', SaleLineType::GIFT_CARD_REDEMPTION)
            ->delete();

        return $this->totalsRecalculator->recalculate($checkout);
    }

    private function alreadyApplied(CommerceCheckout $checkout, string $giftCardId): bool
    {
        return CommerceCheckoutLine::query()
            ->where('checkout_id', $checkout->id)
            ->where('line_type', SaleLineType::GIFT_CARD_REDEMPTION)
            ->where('reference_id', $giftCardId)
            ->exists();
    }
}
