<?php

namespace App\Domains\Pos\Services;

use App\Domains\Pos\Enums\GiftCardStatus;
use App\Domains\Pos\Enums\GiftCardTransactionType;
use App\Domains\Pos\Models\GiftCard;
use App\Domains\Pos\Models\GiftCardTransaction;
use App\Shared\Commerce\Enums\SaleLineType;
use App\Shared\Commerce\Models\CommerceCheckout;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    public function __construct(
        private readonly PosScopeValidator $scope,
        private readonly GiftCardCodeGenerator $codeGenerator,
    ) {}

    public function findByCode(string $code): ?GiftCard
    {
        return GiftCard::query()
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function findByCodeOrFail(string $code): GiftCard
    {
        $card = $this->findByCode($code);

        if ($card === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => ['Gift card not found.'],
            ]);
        }

        $this->scope->assertTenantModel($card);

        return $card;
    }

    public function issueFromCheckout(CommerceCheckout $checkout, ?string $teamMemberId = null): array
    {
        $issued = [];

        foreach ($checkout->lines as $line) {
            if ($line->line_type !== SaleLineType::GIFT_CARD_SALE) {
                continue;
            }

            $amount = $line->line_total_cents;

            if ($amount <= 0) {
                continue;
            }

            $card = GiftCard::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'code' => $this->codeGenerator->generate($checkout->tenant_id),
                'initial_balance_cents' => $amount,
                'current_balance_cents' => $amount,
                'status' => GiftCardStatus::ACTIVE,
                'issued_checkout_id' => $checkout->id,
                'issued_to_client_id' => $checkout->client_id,
            ]);

            GiftCardTransaction::query()->create([
                'tenant_id' => $checkout->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransactionType::ISSUE,
                'amount_cents' => $amount,
                'commerce_checkout_id' => $checkout->id,
                'created_by_team_member_id' => $teamMemberId,
                'notes' => 'Issued on checkout completion',
            ]);

            $line->reference_type = 'gift_card';
            $line->reference_id = $card->id;
            $line->pricing_snapshot = array_merge($line->pricing_snapshot ?? [], [
                'gift_card_id' => $card->id,
                'gift_card_code' => $card->code,
            ]);
            $line->save();

            $issued[] = $card;
        }

        return $issued;
    }

    public function restoreBalance(GiftCard $card, int $amountCents, string $checkoutId, ?string $refundId, ?string $teamMemberId): void
    {
        if ($amountCents <= 0) {
            return;
        }

        DB::transaction(function () use ($card, $amountCents, $checkoutId, $refundId, $teamMemberId) {
            $card->current_balance_cents += $amountCents;
            $card->status = GiftCardStatus::ACTIVE;
            $card->save();

            GiftCardTransaction::query()->create([
                'tenant_id' => $card->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransactionType::REFUND_RESTORE,
                'amount_cents' => $amountCents,
                'commerce_checkout_id' => $checkoutId,
                'payment_refund_id' => $refundId,
                'created_by_team_member_id' => $teamMemberId,
                'notes' => 'Balance restored after refund',
            ]);
        });
    }
}
