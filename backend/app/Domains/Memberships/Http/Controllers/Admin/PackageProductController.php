<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Http\Resources\PackageProductResource;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Domains\Memberships\Services\PackageProductService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageProductController extends Controller
{
    public function __construct(
        private readonly PackageProductService $packageService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(MembershipPlanStatus::all())],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(PackageProductResource::collection($this->packageService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new PackageProductResource($this->packageService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(MembershipPlanStatus::all())],
            'price_cents' => ['required', 'integer', 'min:0'],
            'included_quantity' => ['required', 'numeric', 'min:0.001'],
            'expiry_days' => ['nullable', 'integer', 'min:1'],
            'is_public' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'service_restrictions' => ['nullable', 'array'],
            'service_restrictions.*.booking_service_id' => ['required', 'uuid'],
            'service_restrictions.*.quantity_per_redemption' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $product = $this->packageService->create($data);

        return ApiResponse::success(new PackageProductResource($product), 'Package created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(MembershipPlanStatus::all())],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'included_quantity' => ['sometimes', 'numeric', 'min:0.001'],
            'expiry_days' => ['nullable', 'integer', 'min:1'],
            'is_public' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'service_restrictions' => ['nullable', 'array'],
            'service_restrictions.*.booking_service_id' => ['required', 'uuid'],
            'service_restrictions.*.quantity_per_redemption' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $product = $this->packageService->update($this->scope->findPackageProduct($id), $data);

        return ApiResponse::success(new PackageProductResource($product), 'Package updated');
    }

    public function archive(string $id): JsonResponse
    {
        $product = $this->packageService->archive($this->scope->findPackageProduct($id));

        return ApiResponse::success(new PackageProductResource($product), 'Package archived');
    }
}
