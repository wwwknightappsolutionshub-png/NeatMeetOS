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
            'ai_hairstyle_landing' => (bool) ($catalog['ai_hairstyle_landing'] ?? false),
            'booking_policy' => $catalog['booking_policy'] ?? null,
            'reservation_payment' => $catalog['reservation_payment'] ?? null,
        ])->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=300');
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

    public function lookupGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:40'],
        ]);

        return ApiResponse::success($this->booking->lookupGuestContactFields($validated['phone']));
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_service_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'team_member_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'pricing_tier' => ['nullable', 'string', 'in:regular,membership,loyalty'],
            'member_token' => ['nullable', 'string', 'max:128'],
            'reservation_document_id' => ['nullable', 'uuid'],
            'payment_method' => ['nullable', 'string', 'in:transfer,stripe,google_pay'],
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

        $changeRequest = $this->manage->requestCancel(
            $bookingReference,
            $validated['token'],
            $validated['reason'] ?? null,
        );

        return ApiResponse::success(
            new \App\Domains\Booking\Http\Resources\BookingChangeRequestResource($changeRequest),
            'Cancel request submitted',
            201,
        );
    }

    public function showChangeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:64'],
        ]);

        $changeRequest = app(\App\Domains\Booking\Services\BookingChangeRequestService::class)
            ->findByIdAndToken($validated['id'], $validated['token']);

        return ApiResponse::success(
            new \App\Domains\Booking\Http\Resources\BookingChangeRequestResource($changeRequest),
        );
    }

    public function resolveChangeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:64'],
            'action' => ['required', 'string', 'in:accept,decline'],
        ]);

        $service = app(\App\Domains\Booking\Services\BookingChangeRequestService::class);
        $changeRequest = $service->findByIdAndToken($validated['id'], $validated['token']);

        $result = $validated['action'] === 'accept'
            ? $service->accept($changeRequest, \App\Domains\Booking\Models\BookingChangeRequest::RESOLVED_VIA_LINK)
            : $service->decline($changeRequest, \App\Domains\Booking\Models\BookingChangeRequest::RESOLVED_VIA_LINK);

        return ApiResponse::success(
            new \App\Domains\Booking\Http\Resources\BookingChangeRequestResource($result),
            $validated['action'] === 'accept' ? 'Request accepted' : 'Request declined',
        );
    }
}
