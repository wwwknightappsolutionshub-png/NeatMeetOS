<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Models\StaffSosAlert;
use App\Domains\Booking\Services\StaffSosAlertService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffSosAlertController extends Controller
{
    public function __construct(
        private readonly StaffSosAlertService $alerts,
    ) {}

    public function index(): JsonResponse
    {
        $items = $this->alerts->listActive()->map(fn (StaffSosAlert $alert) => $this->serialize($alert));

        return ApiResponse::success(['items' => $items]);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $teamMember = $request->attributes->get('team_member');
        $alert = $this->alerts->acknowledge($this->alerts->find($id), $teamMember?->id);

        return ApiResponse::success($this->serialize($alert), 'SOS acknowledged');
    }

    public function shift(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'in:10,20,30,45'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $result = $this->alerts->shiftAppointment(
            $this->alerts->find($id),
            (int) $data['minutes'],
            $teamMember?->id,
        );

        return ApiResponse::success([
            'alert' => $this->serialize($result['alert']),
            'appointment_id' => $result['appointment']->id,
            'starts_at' => $result['appointment']->starts_at?->toIso8601String(),
        ], 'Appointment shifted and client notified');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StaffSosAlert $alert): array
    {
        $appointment = $alert->appointment;

        return [
            'id' => $alert->id,
            'kind' => $alert->kind,
            'status' => $alert->status,
            'title' => $alert->title,
            'body' => $alert->body,
            'payload' => $alert->payload_json ?? [],
            'allow_shift' => (bool) (($alert->payload_json['allow_shift'] ?? false)),
            'shift_minutes' => $alert->payload_json['shift_minutes'] ?? StaffSosAlertService::SHIFT_MINUTES,
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'booking_reference' => $appointment->booking_reference,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'client_name' => $appointment->client?->resolvedDisplayName(),
                'provider_name' => $appointment->teamMember?->display_name,
                'location_name' => $appointment->location?->name,
                'services' => $appointment->serviceLines?->pluck('service_name')->values() ?? [],
            ] : null,
            'created_at' => $alert->created_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
        ];
    }
}
