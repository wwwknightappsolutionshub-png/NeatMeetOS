<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\AppointmentBookingService;
use App\Domains\Booking\Services\AppointmentRebookService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly AppointmentRebookService $rebookService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'location_id' => ['nullable', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(Appointment::statuses())],
            'booking_source' => ['nullable', Rule::in(Appointment::bookingSources())],
            'walk_in_stage' => ['nullable', Rule::in(Appointment::walkInStages())],
        ]);

        $appointments = $this->bookingService->list($filters);

        return ApiResponse::success(AppointmentResource::collection($appointments));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new AppointmentResource($this->bookingService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'team_member_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'status' => ['nullable', Rule::in(Appointment::statuses())],
            'booking_source' => ['nullable', Rule::in(Appointment::bookingSources())],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required', 'uuid'],
            'services.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $appointment = $this->bookingService->create($data, $teamMember?->id);

        return ApiResponse::success(new AppointmentResource($appointment), 'Appointment created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['sometimes', 'uuid'],
            'team_member_id' => ['sometimes', 'uuid'],
            'location_id' => ['sometimes', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['sometimes', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required_with:services', 'uuid'],
            'services.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $appointment = $this->bookingService->update($this->bookingService->find($id), $data);

        return ApiResponse::success(new AppointmentResource($appointment), 'Appointment updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::statuses())],
            'no_show_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = $this->bookingService->updateStatus(
            $this->bookingService->find($id),
            $data['status'],
            $data['no_show_reason'] ?? null,
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Status updated');
    }

    public function correctStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Appointment::statuses())],
            'correction_note' => ['required', 'string', 'max:500'],
        ]);

        $appointment = $this->bookingService->correctStatus(
            $this->bookingService->find($id),
            $data['status'],
            $data['correction_note'],
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Status corrected');
    }

    public function rebook(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'team_member_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(Appointment::statuses())],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $appointment = $this->rebookService->rebook(
            $this->bookingService->find($id),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Appointment rebooked', 201);
    }

    public function reassignWorkspace(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['nullable', 'uuid'],
        ]);

        $appointment = $this->bookingService->reassignWorkspace(
            $this->bookingService->find($id),
            $data['workspace_id'] ?? null,
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Workspace reassigned');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = $this->bookingService->cancel(
            $this->bookingService->find($id),
            $data['cancellation_reason'] ?? null,
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Appointment cancelled');
    }

    public function updateDepositStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'deposit_status' => ['required', Rule::in(Appointment::depositStatuses())],
        ]);

        $appointment = $this->bookingService->updateDepositStatus(
            $this->bookingService->find($id),
            $data['deposit_status'],
        );

        return ApiResponse::success(new AppointmentResource($appointment), 'Deposit status updated');
    }
}
