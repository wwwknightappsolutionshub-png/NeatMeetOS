<?php

namespace App\Domains\Ecommerce\Http\Controllers;

use App\Domains\Ecommerce\Http\Resources\EcommerceOrderResource;
use App\Domains\Ecommerce\Http\Resources\PublicEcommerceProductResource;
use App\Domains\Ecommerce\Services\EcommerceOrderService;
use App\Domains\Ecommerce\Services\PublicEcommerceService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(
        private readonly PublicEcommerceService $catalogService,
        private readonly EcommerceOrderService $orderService,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'uuid'],
            'carousel' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('carousel')) {
            $filters['carousel'] = true;
        }

        $products = $this->catalogService->listProducts($filters);

        if (! empty($filters['location_id'])) {
            $products->each(function ($product) use ($filters) {
                $product->setAttribute(
                    'available_quantity',
                    $this->catalogService->availableQuantity(
                        $product->inventory_item_id,
                        $filters['location_id'],
                    ),
                );
            });
        }

        // Resolve to a plain list so the SPA always receives `data: [...]`.
        return ApiResponse::success(
            PublicEcommerceProductResource::collection($products)->resolve(),
        );
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ecommerce_product_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $order = $this->orderService->placeOrder($data);

        return ApiResponse::success(new EcommerceOrderResource($order), 'Order placed', 201);
    }
}
