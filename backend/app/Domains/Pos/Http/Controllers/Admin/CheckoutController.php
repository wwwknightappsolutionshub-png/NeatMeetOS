<?php

namespace App\Domains\Pos\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Pos\Http\Resources\CheckoutListResource;
use App\Domains\Pos\Http\Resources\CheckoutResource;
use App\Domains\Pos\Services\CheckoutCompletionService;
use App\Domains\Pos\Services\CheckoutDepositService;
use App\Domains\Pos\Services\CheckoutImportService;
use App\Domains\Pos\Services\CheckoutLineService;
use App\Domains\Pos\Services\CheckoutPaymentService;
use App\Domains\Pos\Services\CheckoutService;
use App\Domains\Pos\Services\CheckoutVoidService;
use App\Domains\Pos\Enums\DiscountType;
use App\Shared\Commerce\Enums\CheckoutStatus;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CheckoutLineService $lineService,
        private readonly CheckoutImportService $importService,
        private readonly CheckoutDepositService $depositService,
        private readonly CheckoutPaymentService $paymentService,
        private readonly CheckoutCompletionService $completionService,
        private readonly CheckoutVoidService $voidService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(CheckoutStatus::all())],
            'location_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            CheckoutListResource::collection($this->checkoutService->list($filters)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $checkout = $this->checkoutService->createDraft($data, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout), 'Created', 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new CheckoutResource($this->checkoutService->find($id)));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in([CheckoutStatus::OPEN])],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $checkout = $this->checkoutService->update($id, $data, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function addServiceLine(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'booking_service_id' => ['nullable', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'discount_type' => ['nullable', Rule::in(DiscountType::all())],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'discount_authorised_by_team_member_id' => ['nullable', 'uuid'],
        ]);

        $checkout = $this->lineService->addServiceLine($id, $data);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function addRetailLine(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $checkout = $this->lineService->addRetailLine($id, $data);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function addGiftCardLine(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'issued_to_client_id' => ['nullable', 'uuid'],
        ]);

        $checkout = $this->lineService->addGiftCardLine($id, $data);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function updateLine(Request $request, string $id, string $lineId): JsonResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'discount_type' => ['nullable', Rule::in(DiscountType::all())],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'discount_authorised_by_team_member_id' => ['nullable', 'uuid'],
        ]);

        $checkout = $this->lineService->updateLine($id, $lineId, $data);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removeLine(string $id, string $lineId): JsonResponse
    {
        $checkout = $this->lineService->removeLine($id, $lineId);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function importAppointment(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => ['required', 'uuid'],
        ]);

        $checkout = $this->importService->importAppointment($id, $data['appointment_id']);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function applyDepositCredit(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'deposit_record_id' => ['nullable', 'uuid'],
        ]);

        $checkout = $this->depositService->applyDepositCredit($id, $data['deposit_record_id'] ?? null);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removeDepositCredit(string $id): JsonResponse
    {
        $checkout = $this->depositService->removeDepositCredit($id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function recordPayments(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'tenders' => ['required', 'array', 'min:1'],
            'tenders.*.amount_cents' => ['required', 'integer', 'min:1'],
            'tenders.*.payment_method_type' => ['required', 'string', 'in:cash,card_manual,payment_link'],
            'tenders.*.payment_method_label' => ['nullable', 'string', 'max:100'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $checkout = $this->paymentService->recordPayments(
            $id,
            $data['tenders'],
            $teamMember?->id,
        );

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function listPayments(string $id): JsonResponse
    {
        $payments = $this->paymentService->listPayments($id);

        return ApiResponse::success(
            \App\Domains\Payments\Http\Resources\PaymentTransactionResource::collection(collect($payments)),
        );
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');

        $checkout = $this->completionService->complete($id, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function void(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');

        $checkout = $this->voidService->void($id, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }
}
