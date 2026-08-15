<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\BookingChangeRequestResource;
use App\Domains\Booking\Http\Resources\BookingPolicySettingResource;
use App\Domains\Booking\Models\BookingChangeRequest;
use App\Domains\Booking\Services\AppointmentBookingService;
use App\Domains\Booking\Services\BookingChangeRequestService;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingChangeRequestController extends Controller
{
    public function __construct(
        private readonly BookingChangeRequestService $changeRequests,
        private readonly BookingPolicyService $policy,
        private readonly AppointmentBookingService $appointments,
    ) {}

    public function policy(): JsonResponse
    {
        return ApiResponse::success(
            new BookingPolicySettingResource($this->policy->get()),
        );
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'items' => BookingChangeRequestResource::collection($this->changeRequests->listPending()),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            new BookingChangeRequestResource($this->changeRequests->find($id)),
        );
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $result = $this->changeRequests->accept(
            $this->changeRequests->find($id),
            BookingChangeRequest::RESOLVED_VIA_ADMIN,
            $teamMember?->id,
        );

        return ApiResponse::success(
            new BookingChangeRequestResource($result),
            'Change request accepted',
        );
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $result = $this->changeRequests->decline(
            $this->changeRequests->find($id),
            BookingChangeRequest::RESOLVED_VIA_ADMIN,
            $teamMember?->id,
        );

        return ApiResponse::success(
            new BookingChangeRequestResource($result),
            'Change request declined',
        );
    }

    public function postpone(Request $request, string $appointmentId): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'team_member_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $appointment = $this->appointments->find($appointmentId);
        $result = $this->changeRequests->requestTenantPostpone(
            $appointment,
            $validated,
            $teamMember?->id,
        );

        return ApiResponse::success(
            new BookingChangeRequestResource($result),
            'Postpone request sent',
            201,
        );
    }
}
