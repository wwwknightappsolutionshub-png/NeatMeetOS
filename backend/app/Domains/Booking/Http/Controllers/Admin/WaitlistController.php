<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Booking\Http\Resources\WaitlistEntryResource;
use App\Domains\Booking\Models\WaitlistEntry;
use App\Domains\Booking\Services\WaitlistService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlistService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(WaitlistEntry::statuses())],
            'location_id' => ['nullable', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'booking_service_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(WaitlistEntryResource::collection($this->waitlistService->list($filters)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(new WaitlistEntryResource($this->waitlistService->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'team_member_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'workspace_type_preference' => ['nullable', 'string', 'max:50'],
            'preferred_starts_at' => ['nullable', 'date'],
            'preferred_ends_at' => ['nullable', 'date'],
            'availability_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'services' => ['nullable', 'array'],
            'services.*.booking_service_id' => ['required_with:services', 'uuid'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $entry = $this->waitlistService->create($data, $teamMember?->id);

        return ApiResponse::success(new WaitlistEntryResource($entry), 'Waitlist entry created', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(WaitlistEntry::statuses())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'availability_notes' => ['nullable', 'string', 'max:2000'],
            'preferred_starts_at' => ['nullable', 'date'],
            'preferred_ends_at' => ['nullable', 'date'],
            'team_member_id' => ['nullable', 'uuid'],
        ]);

        $entry = $this->waitlistService->update($this->waitlistService->find($id), $data);

        return ApiResponse::success(new WaitlistEntryResource($entry), 'Waitlist entry updated');
    }

    public function fulfill(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'team_member_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'services' => ['nullable', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required_with:services', 'uuid'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $result = $this->waitlistService->fulfill(
            $this->waitlistService->find($id),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success([
            'waitlist' => new WaitlistEntryResource($result['waitlist']),
            'appointment' => new AppointmentResource($result['appointment']),
        ], 'Waitlist fulfilled', 201);
    }
}
