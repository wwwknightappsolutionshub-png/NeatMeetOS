<?php

namespace App\Domains\Ecommerce\Http\Controllers\Admin;

use App\Domains\Ecommerce\Enums\EcommerceProductStatus;
use App\Domains\Ecommerce\Http\Resources\EcommerceProductResource;
use App\Domains\Ecommerce\Services\EcommerceProductService;
use App\Domains\Ecommerce\Services\EcommerceScopeValidator;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EcommerceProductController extends Controller
{
    public function __construct(
        private readonly EcommerceProductService $productService,
        private readonly EcommerceScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(EcommerceProductStatus::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(
            EcommerceProductResource::collection($this->productService->list($filters)),
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            new EcommerceProductResource($this->productService->find($id)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'show_on_booking_carousel' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = $this->productService->create($data);

        return ApiResponse::success(new EcommerceProductResource($product), 'Product created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['sometimes', 'uuid'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'show_on_booking_carousel' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = $this->productService->update($this->scope->findProduct($id), $data);

        return ApiResponse::success(new EcommerceProductResource($product), 'Product updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(EcommerceProductStatus::all())],
        ]);

        $product = $this->scope->findProduct($id);

        $product = $data['status'] === EcommerceProductStatus::ARCHIVED
            ? $this->productService->archive($product)
            : $this->productService->activate($product);

        return ApiResponse::success(new EcommerceProductResource($product), 'Product status updated');
    }
}
