<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Enums\ConsumptionMode;
use App\Domains\Inventory\Http\Resources\ServiceConsumptionRuleResource;
use App\Domains\Inventory\Services\InventoryScopeValidator;
use App\Domains\Inventory\Services\ServiceConsumptionRuleService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceConsumptionRuleController extends Controller
{
    public function __construct(
        private readonly ServiceConsumptionRuleService $ruleService,
        private readonly InventoryScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'booking_service_id' => ['nullable', 'uuid'],
            'inventory_item_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            ServiceConsumptionRuleResource::collection($this->ruleService->list($filters)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_service_id' => ['required', 'uuid'],
            'inventory_item_id' => ['required', 'uuid'],
            'quantity_required' => ['required', 'numeric', 'min:0.001'],
            'consumption_mode' => ['nullable', Rule::in(ConsumptionMode::all())],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $this->ruleService->create($data);

        return ApiResponse::success(new ServiceConsumptionRuleResource($rule), 'Rule created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'booking_service_id' => ['sometimes', 'uuid'],
            'inventory_item_id' => ['sometimes', 'uuid'],
            'quantity_required' => ['sometimes', 'numeric', 'min:0.001'],
            'consumption_mode' => ['nullable', Rule::in(ConsumptionMode::all())],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $this->ruleService->update($this->scope->findConsumptionRule($id), $data);

        return ApiResponse::success(new ServiceConsumptionRuleResource($rule), 'Rule updated');
    }

    public function archive(string $id): JsonResponse
    {
        $rule = $this->ruleService->archive($this->scope->findConsumptionRule($id));

        return ApiResponse::success(new ServiceConsumptionRuleResource($rule), 'Rule archived');
    }
}
