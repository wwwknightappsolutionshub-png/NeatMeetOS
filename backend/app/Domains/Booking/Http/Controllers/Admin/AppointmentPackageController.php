<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Http\Resources\ClientPackageRedemptionResource;
use App\Domains\Memberships\Services\BookingMembershipService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentPackageController extends Controller
{
    public function __construct(private readonly BookingMembershipService $bookingMembership) {}

    public function eligiblePackages(Request $request, string $appointmentId): JsonResponse
    {
        $filters = $request->validate([
            'booking_service_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(
            $this->bookingMembership->appointmentPackageSummary($appointmentId),
        );
    }

    public function reservePackage(Request $request, string $appointmentId, string $serviceLineId): JsonResponse
    {
        $data = $request->validate([
            'client_package_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $redemption = $this->bookingMembership->reservePackage(
            $appointmentId,
            $serviceLineId,
            $data['client_package_id'],
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            $teamMember?->id,
        );

        return ApiResponse::success(new ClientPackageRedemptionResource($redemption->load('clientPackage')), 'Reserved', 201);
    }

    public function releasePackage(Request $request, string $appointmentId, string $serviceLineId): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $this->bookingMembership->releasePackage($appointmentId, $serviceLineId, $teamMember?->id);

        return ApiResponse::success(
            $this->bookingMembership->appointmentPackageSummary($appointmentId),
            'Released',
        );
    }
}
