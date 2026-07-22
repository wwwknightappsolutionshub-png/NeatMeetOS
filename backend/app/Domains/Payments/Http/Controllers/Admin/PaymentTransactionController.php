<?php

namespace App\Domains\Payments\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentMethodType;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Domains\Payments\Http\Resources\PaymentTransactionResource;
use App\Domains\Payments\Services\PaymentScopeValidator;
use App\Domains\Payments\Services\PaymentTransactionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentTransactionController extends Controller
{
    public function __construct(
        private readonly PaymentTransactionService $transactionService,
        private readonly PaymentScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PaymentTransactionStatus::all())],
            'transaction_type' => ['nullable', Rule::in(PaymentTransactionType::all())],
            'client_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            PaymentTransactionResource::collection($this->transactionService->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        $transaction = $this->transactionService->find($id);
        $this->scope->assertTenantModel($transaction);

        return ApiResponse::success(new PaymentTransactionResource($transaction));
    }

    public function storeManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_type' => ['required', Rule::in(PaymentTransactionType::all())],
            'direction' => ['nullable', Rule::in(PaymentDirection::all())],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'location_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'payment_method_type' => ['nullable', Rule::in(PaymentMethodType::all())],
            'payment_method_label' => ['nullable', 'string', 'max:100'],
            'external_reference' => ['nullable', 'string', 'max:200'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'allocations' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $transaction = $this->transactionService->createManual($data, $teamMember?->id);

        return ApiResponse::success(new PaymentTransactionResource($transaction), 'Payment recorded', 201);
    }

    public function storePaymentLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_type' => ['required', Rule::in(PaymentTransactionType::all())],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'location_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'payment_method_label' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'allocations' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $transaction = $this->transactionService->createPaymentLink($data, $teamMember?->id);

        return ApiResponse::success(new PaymentTransactionResource($transaction), 'Payment link created', 201);
    }

    public function markSucceeded(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $transaction = $this->transactionService->markSucceeded(
            $this->scope->findTransaction($id),
            $teamMember?->id,
        );

        return ApiResponse::success(new PaymentTransactionResource($transaction), 'Payment marked succeeded');
    }

    public function markFailed(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'failure_code' => ['nullable', 'string', 'max:50'],
            'failure_message' => ['nullable', 'string', 'max:500'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $transaction = $this->transactionService->markFailed(
            $this->scope->findTransaction($id),
            $data['failure_code'] ?? null,
            $data['failure_message'] ?? null,
            $teamMember?->id,
        );

        return ApiResponse::success(new PaymentTransactionResource($transaction), 'Payment marked failed');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $transaction = $this->transactionService->cancel(
            $this->scope->findTransaction($id),
            $teamMember?->id,
        );

        return ApiResponse::success(new PaymentTransactionResource($transaction), 'Payment cancelled');
    }
}
