<?php

namespace App\Domains\Pos\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Pos\Http\Resources\CheckoutResource;
use App\Domains\Pos\Services\CheckoutMembershipApplicationService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutMembershipController extends Controller
{
    public function __construct(private readonly CheckoutMembershipApplicationService $membershipApplication) {}

    public function membershipOptions(string $id): JsonResponse
    {
        return ApiResponse::success($this->membershipApplication->membershipOptions($id));
    }

    public function applyWallet(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->applyWallet($id, $data['amount_cents'], $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removeWallet(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->removeWallet($id, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function applyLoyalty(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->applyLoyalty($id, $data['points'], $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removeLoyalty(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->removeLoyalty($id, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function applyPackage(Request $request, string $id, string $lineId): JsonResponse
    {
        $data = $request->validate([
            'client_package_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->applyPackageToLine(
            $id,
            $lineId,
            $data['client_package_id'],
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            $teamMember?->id,
        );

        return ApiResponse::success(new CheckoutResource($checkout));
    }

    public function removePackage(Request $request, string $id, string $lineId): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $checkout = $this->membershipApplication->removePackageFromLine($id, $lineId, $teamMember?->id);

        return ApiResponse::success(new CheckoutResource($checkout));
    }
}
