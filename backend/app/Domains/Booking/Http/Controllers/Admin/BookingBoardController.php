<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Services\BookingBoardService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingBoardController extends Controller
{
    public function __construct(private readonly BookingBoardService $boardService) {}

    public function day(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'location_id' => ['nullable', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(Appointment::statuses())],
            'booking_source' => ['nullable', Rule::in(Appointment::bookingSources())],
        ]);

        $board = $this->boardService->dayBoard($filters);

        return ApiResponse::success([
            'date' => $board['date'],
            'summary' => $board['summary'],
            'workspace_occupancy' => $board['workspace_occupancy'],
            'appointments' => AppointmentResource::collection($board['appointments']),
        ]);
    }
}
