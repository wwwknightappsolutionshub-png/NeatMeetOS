<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\BookableServiceResource;
use App\Domains\Booking\Services\BookableServiceCatalogService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookableServiceController extends Controller
{
    public function __construct(private readonly BookableServiceCatalogService $catalogService) {}

    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        return ApiResponse::success(BookableServiceResource::collection($this->catalogService->list($activeOnly)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['required', 'string', 'max:2048'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'base_price_cents' => ['nullable', 'integer', 'min:0'],
            'membership_price_cents' => ['nullable', 'integer', 'min:0'],
            'loyalty_price_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_bookable_online' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'deposit_required' => ['sometimes', 'boolean'],
            'deposit_amount_cents' => ['nullable', 'integer', 'min:0'],
            'min_lead_time_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'cancellation_window_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        $service = $this->catalogService->create($data);

        return ApiResponse::success(new BookableServiceResource($service), 'Service created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $existing = $this->catalogService->find($id);

        $imageRules = ['nullable', 'string', 'max:2048'];
        if ($existing->image_url === null || $existing->image_url === '') {
            $imageRules = ['required', 'string', 'max:2048'];
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => $imageRules,
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'base_price_cents' => ['nullable', 'integer', 'min:0'],
            'membership_price_cents' => ['nullable', 'integer', 'min:0'],
            'loyalty_price_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_bookable_online' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'deposit_required' => ['sometimes', 'boolean'],
            'deposit_amount_cents' => ['nullable', 'integer', 'min:0'],
            'min_lead_time_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'cancellation_window_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        $service = $this->catalogService->update($existing, $data);

        return ApiResponse::success(new BookableServiceResource($service), 'Service updated');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $result = $this->catalogService->uploadImage($data['image']);

        return ApiResponse::success($result, 'Uploaded', 201);
    }

    public function archive(string $id): JsonResponse
    {
        $service = $this->catalogService->archive($this->catalogService->find($id));

        return ApiResponse::success(new BookableServiceResource($service), 'Service archived');
    }
}
