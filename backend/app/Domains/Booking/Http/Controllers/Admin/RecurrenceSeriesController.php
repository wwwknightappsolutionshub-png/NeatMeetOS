<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentRecurrenceSeriesResource;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\RecurrenceSeriesService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurrenceSeriesController extends Controller
{
    public function __construct(private readonly RecurrenceSeriesService $recurrenceService) {}

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            new AppointmentRecurrenceSeriesResource($this->recurrenceService->find($id)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'team_member_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'interval_weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'occurrence_count' => ['nullable', 'integer', 'min:2', 'max:52'],
            'end_date' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['nullable', Rule::in(Appointment::statuses())],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required', 'uuid'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $result = $this->recurrenceService->create($data, $teamMember?->id);

        return ApiResponse::success([
            'series' => new AppointmentRecurrenceSeriesResource($result['series']),
            'created_appointment_ids' => $result['created'],
            'skipped' => $result['skipped'],
        ], 'Recurrence series created', 201);
    }

    public function cancel(string $id): JsonResponse
    {
        $series = $this->recurrenceService->cancelFuture($this->recurrenceService->find($id));

        return ApiResponse::success(new AppointmentRecurrenceSeriesResource($series), 'Series cancelled');
    }
}
