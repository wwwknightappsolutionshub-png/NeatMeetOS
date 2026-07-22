<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Enums\InventoryItemStatus;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Http\Resources\InventoryItemResource;
use App\Domains\Inventory\Services\InventoryItemService;
use App\Domains\Inventory\Services\InventoryScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function __construct(
        private readonly InventoryItemService $itemService,
        private readonly InventoryScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(InventoryItemStatus::all())],
            'item_type' => ['nullable', Rule::in(InventoryItemType::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(
            InventoryItemResource::collection($this->itemService->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->itemService->find($id);
        $this->scope->assertTenantModel($item);

        return ApiResponse::success(new InventoryItemResource($item));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'item_type' => ['required', Rule::in(InventoryItemType::all())],
            'brand' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'unit_size' => ['nullable', 'string', 'max:50'],
            'cost_price_cents' => ['nullable', 'integer', 'min:0'],
            'retail_price_cents' => ['nullable', 'integer', 'min:0'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'preferred_supplier_id' => ['nullable', 'uuid'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $item = $this->itemService->create($data);

        return ApiResponse::success(new InventoryItemResource($item), 'Item created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'item_type' => ['sometimes', Rule::in(InventoryItemType::all())],
            'brand' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'unit_size' => ['nullable', 'string', 'max:50'],
            'cost_price_cents' => ['nullable', 'integer', 'min:0'],
            'retail_price_cents' => ['nullable', 'integer', 'min:0'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'preferred_supplier_id' => ['nullable', 'uuid'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $item = $this->itemService->update($this->scope->findItem($id), $data);

        return ApiResponse::success(new InventoryItemResource($item), 'Item updated');
    }

    public function archive(string $id): JsonResponse
    {
        $item = $this->itemService->archive($this->scope->findItem($id));

        return ApiResponse::success(new InventoryItemResource($item), 'Item archived');
    }

    public function activate(string $id): JsonResponse
    {
        $item = $this->itemService->activate($this->scope->findItem($id));

        return ApiResponse::success(new InventoryItemResource($item), 'Item activated');
    }
}
