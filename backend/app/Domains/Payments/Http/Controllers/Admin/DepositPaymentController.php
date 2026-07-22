<?php

namespace App\Domains\Payments\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Payments\Enums\PaymentMethodType;
use App\Domains\Payments\Http\Resources\PaymentRefundResource;
use App\Domains\Payments\Http\Resources\PaymentTransactionResource;
use App\Domains\Payments\Services\DepositPaymentService;
use App\Domains\Payments\Services\PaymentRefundService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepositPaymentController extends Controller
{
    public function __construct(private readonly DepositPaymentService $depositService) {}

    public function show(string $appointmentId): JsonResponse
    {
        return ApiResponse::success($this->depositService->inspect($appointmentId));
    }

    public function pay(Request $request, string $appointmentId): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'payment_method_type' => ['nullable', Rule::in(PaymentMethodType::all())],
            'payment_method_label' => ['nullable', 'string', 'max:100'],
            'external_reference' => ['nullable', 'string', 'max:200'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $data['appointment_id'] = $appointmentId;
        $teamMember = $request->attributes->get('team_member');
        $result = $this->depositService->recordPayment($data, $teamMember?->id);

        return ApiResponse::success([
            'deposit_record' => $result['deposit_record'],
            'payment_transaction' => new PaymentTransactionResource($result['payment_transaction']),
            'appointment' => $result['appointment']->only(['id', 'deposit_status', 'deposit_required_cents']),
        ], 'Deposit recorded', 201);
    }

    public function waive(Request $request, string $appointmentId): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $teamMember = $request->attributes->get('team_member');
        $result = $this->depositService->waive($appointmentId, $data['notes'] ?? null, $teamMember?->id);

        return ApiResponse::success($result, 'Deposit waived');
    }

    public function refund(Request $request, string $appointmentId): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $refundService = app(PaymentRefundService::class);
        $result = $refundService->refundAppointmentDeposit($appointmentId, $data, $teamMember?->id);

        return ApiResponse::success([
            'refund' => new PaymentRefundResource($result['refund']),
            'deposit_record' => $result['deposit_record'],
        ], 'Deposit refunded', 201);
    }
}
