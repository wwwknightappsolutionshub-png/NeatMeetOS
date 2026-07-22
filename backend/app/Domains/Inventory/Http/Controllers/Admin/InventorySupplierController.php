<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Http\Resources\InventorySupplierResource;
use App\Domains\Inventory\Services\InventoryScopeValidator;
use App\Domains\Inventory\Services\InventorySupplierService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventorySupplierController extends Controller
{
    public function __construct(
        private readonly InventorySupplierService $supplierService,
        private readonly InventoryScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(
            InventorySupplierResource::collection($this->supplierService->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            new InventorySupplierResource($this->supplierService->find($id)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier = $this->supplierService->create($data);

        return ApiResponse::success(new InventorySupplierResource($supplier), 'Supplier created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $supplier = $this->supplierService->update($this->scope->findSupplier($id), $data);

        return ApiResponse::success(new InventorySupplierResource($supplier), 'Supplier updated');
    }

    public function archive(string $id): JsonResponse
    {
        $supplier = $this->supplierService->archive($this->scope->findSupplier($id));

        return ApiResponse::success(new InventorySupplierResource($supplier), 'Supplier archived');
    }

    public function activate(string $id): JsonResponse
    {
        $supplier = $this->supplierService->activate($this->scope->findSupplier($id));

        return ApiResponse::success(new InventorySupplierResource($supplier), 'Supplier activated');
    }
}
