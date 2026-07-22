<?php

namespace App\Domains\Ecommerce\Http\Controllers\Admin;

use App\Domains\Ecommerce\Enums\EcommerceOrderStatus;
use App\Domains\Ecommerce\Enums\EcommercePaymentStatus;
use App\Domains\Ecommerce\Http\Resources\EcommerceOrderResource;
use App\Domains\Ecommerce\Services\EcommerceOrderService;
use App\Domains\Ecommerce\Services\EcommerceScopeValidator;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceOrderController extends Controller
{
    public function __construct(
        private readonly EcommerceOrderService $orderService,
        private readonly EcommerceScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(EcommerceOrderStatus::all())],
            'location_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            EcommerceOrderResource::collection($this->orderService->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            new EcommerceOrderResource($this->orderService->find($id)),
        );
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(EcommerceOrderStatus::adminUpdatable())],
            'payment_status' => ['nullable', Rule::in(EcommercePaymentStatus::all())],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $order = $this->orderService->updateStatus(
            $this->scope->findOrder($id),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new EcommerceOrderResource($order), 'Order status updated');
    }
}
