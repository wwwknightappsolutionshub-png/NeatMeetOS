<?php

namespace App\Domains\Booking\Http\Controllers\PublicBooking;

use App\Domains\Booking\Http\Resources\BookableServiceResource;
use App\Domains\Booking\Http\Resources\PublicOnlineAppointmentResource;
use App\Domains\Booking\Services\OnlineBookingService;
use App\Domains\Booking\Services\PublicBookingManageService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineBookingController extends Controller
{
    public function __construct(
        private readonly OnlineBookingService $booking,
        private readonly PublicBookingManageService $manage,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'uuid'],
        ]);

        $catalog = $this->booking->catalog($validated['location_id'] ?? null);

        return ApiResponse::success([
            'tenant' => $catalog['tenant'],
            'locations' => $catalog['locations']->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'slug' => $l->slug,
                'timezone' => $l->timezone,
                'address' => $l->address,
                'contact_phone' => $l->contact_phone,
                'opening_hours' => $l->opening_hours,
            ])->values(),
            'services' => BookableServiceResource::collection($catalog['services']),
            'providers' => $catalog['providers']->map(fn ($p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'primary_location_id' => $p->primary_location_id,
            ])->values(),
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_service_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'team_member_id' => ['nullable', 'uuid'],
        ]);

        $slots = $this->booking->availableSlots(
            $validated['booking_service_id'],
            $validated['location_id'],
            $validated['date'],
            $validated['team_member_id'] ?? null,
        );

        return ApiResponse::success(['slots' => $slots]);
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_service_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'team_member_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'pricing_tier' => ['nullable', 'string', 'in:regular,membership,loyalty'],
            'member_token' => ['nullable', 'string', 'max:128'],
        ]);

        $appointment = $this->booking->book($validated)->load([
            'client', 'teamMember', 'location', 'workspace', 'serviceLines',
        ]);

        return ApiResponse::success(
            new PublicOnlineAppointmentResource($appointment, $this->manage->manageLinks($appointment)),
            'Appointment booked',
            201,
        );
    }

    public function showManaged(Request $request, string $bookingReference): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $appointment = $this->manage->findByReferenceAndToken($bookingReference, $validated['token']);

        return ApiResponse::success(
            new PublicOnlineAppointmentResource($appointment, $this->manage->manageLinks($appointment)),
        );
    }

    public function cancelManaged(Request $request, string $bookingReference): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = $this->manage->cancel(
            $bookingReference,
            $validated['token'],
            $validated['reason'] ?? null,
        );

        return ApiResponse::success(
            new PublicOnlineAppointmentResource($appointment, $this->manage->manageLinks($appointment)),
            'Appointment cancelled',
        );
    }
}
