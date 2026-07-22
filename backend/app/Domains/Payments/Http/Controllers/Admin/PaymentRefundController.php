<?php

namespace App\Domains\Payments\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Payments\Http\Resources\PaymentRefundResource;
use App\Domains\Payments\Services\PaymentRefundService;
use App\Domains\Payments\Services\PaymentScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentRefundController extends Controller
{
    public function __construct(
        private readonly PaymentRefundService $refundService,
        private readonly PaymentScopeValidator $scope,
    ) {}

    public function index(string $paymentId): JsonResponse
    {
        return ApiResponse::success(
            PaymentRefundResource::collection($this->refundService->listForTransaction($paymentId)),
        );
    }

    public function store(Request $request, string $paymentId): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $refund = $this->refundService->createRefund(
            $this->scope->findTransaction($paymentId),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new PaymentRefundResource($refund), 'Refund created', 201);
    }
}
