<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Http\Resources\ClientPackageResource;
use App\Domains\Memberships\Services\ClientPackageService;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientPackageController extends Controller
{
    public function __construct(
        private readonly ClientPackageService $packageService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(ClientPackageStatus::all())],
        ]);

        return ApiResponse::success(ClientPackageResource::collection($this->packageService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new ClientPackageResource($this->packageService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'package_product_id' => ['required', 'uuid'],
            'quantity_total' => ['nullable', 'numeric', 'min:0.001'],
            'source' => ['nullable', Rule::in(ClientPackageSource::all())],
            'purchased_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $package = $this->packageService->assign($data, $teamMember?->id);

        return ApiResponse::success(new ClientPackageResource($package), 'Client package assigned', 201);
    }

    public function redeem(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'booking_service_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $package = $this->packageService->redeem($this->scope->findClientPackage($id), $data, $teamMember?->id);

        return ApiResponse::success(new ClientPackageResource($package), 'Package redeemed');
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $package = $this->packageService->restore($this->scope->findClientPackage($id), $data, $teamMember?->id);

        return ApiResponse::success(new ClientPackageResource($package), 'Package restored');
    }
}
