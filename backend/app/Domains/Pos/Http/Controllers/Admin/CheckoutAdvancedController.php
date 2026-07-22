<?php

namespace App\Domains\Pos\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Pos\Http\Resources\CheckoutRefundResource;
use App\Domains\Pos\Http\Resources\CheckoutResource;
use App\Domains\Pos\Http\Resources\ReceiptResource;
use App\Domains\Pos\Services\CheckoutRefundService;
use App\Domains\Pos\Services\CheckoutReopenService;
use App\Domains\Pos\Services\CheckoutReturnService;
use App\Domains\Pos\Services\GiftCardRedemptionService;
use App\Domains\Pos\Services\GiftCardService;
use App\Domains\Pos\Services\ReceiptService;
use App\Domains\Pos\Enums\ReceiptDeliveryMethod;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutAdvancedController extends Controller
{
    public function __construct(
        private readonly CheckoutRefundService $refundService,
        private readonly CheckoutReturnService $returnService,
        private readonly CheckoutReopenService $reopenService,
        private readonly GiftCardService $giftCardService,
        private readonly GiftCardRedemptionService $giftCardRedemptionService,
        private readonly ReceiptService $receiptService,
    ) {}

    public function listRefunds(string $id): JsonResponse
    {
        return ApiResponse::success(
            CheckoutRefundResource::collection($this->refundService->listForCheckout($id)),
        );
    }

    public function createRefund(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_transaction_id' => ['nullable', 'uuid'],
            'line_id' => ['nullable', 'uuid'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $result = $this->refundService->createRefund($id, $data, $teamMember?->id);

        return ApiResponse::success([
            'refund' => new CheckoutRefundResource($result['refund']),
            'checkout' => new CheckoutResource($result['checkout']),
        ], 'Created', 201);
    }

    public function processReturn(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'line_id' => ['required', 'uuid'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_immediately' => ['nullable', 'boolean'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->returnService->processReturn($id, $data, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->reopenService->reopen($id, $data, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function lookupGiftCard(string $code): JsonResponse
    {
        $card = $this->giftCardService->findByCodeOrFail($code);

        return ApiResponse::success([
            'id' => $card->id,
            'code' => $card->code,
            'current_balance_cents' => $card->current_balance_cents,
            'status' => $card->status,
        ]);
    }

    public function applyGiftCard(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        $checkout = $this->giftCardRedemptionService->apply(
            $id,
            $data['code'],
            $data['amount_cents'] ?? null,
        );

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removeGiftCard(string $id): JsonResponse
    {
        $checkout = $this->giftCardRedemptionService->removePending($id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function listReceipts(string $id): JsonResponse
    {
        return ApiResponse::success(
            ReceiptResource::collection($this->receiptService->listForCheckout($id)),
        );
    }

    public function resendReceipt(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'delivery_method' => ['required', Rule::in(ReceiptDeliveryMethod::all())],
            'delivery_target' => ['nullable', 'string', 'max:255'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $receipt = $this->receiptService->resend($id, $data, $teamMember?->id);

        return ApiResponse::success(new ReceiptResource($receipt), 'Created', 201);
    }
}
