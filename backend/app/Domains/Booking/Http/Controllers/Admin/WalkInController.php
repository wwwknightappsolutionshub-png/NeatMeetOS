<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\AppointmentBookingService;
use App\Domains\Booking\Services\WalkInService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalkInController extends Controller
{
    public function __construct(
        private readonly WalkInService $walkInService,
        private readonly AppointmentBookingService $bookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'walk_in_stage' => ['nullable', Rule::in(Appointment::walkInStages())],
            'location_id' => ['nullable', 'uuid'],
            'include_completed' => ['nullable', 'boolean'],
        ]);

        $walkIns = $this->walkInService->list($filters);

        return ApiResponse::success(AppointmentResource::collection($walkIns));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required', 'uuid'],
            'seat_immediately' => ['nullable', 'boolean'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $walkIn = $this->walkInService->create($data, $teamMember?->id);

        return ApiResponse::success(new AppointmentResource($walkIn), 'Walk-in registered', 201);
    }

    public function seat(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'team_member_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['nullable', 'date'],
        ]);

        $appointment = $this->walkInService->seat(
            $this->bookingService->find($id),
            $data,
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Walk-in seated');
    }
}
