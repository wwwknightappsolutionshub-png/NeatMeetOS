<?php

namespace App\Domains\Crm\Http\Controllers\PublicMember;

use App\Domains\Booking\Http\Resources\AppointmentResource;
use App\Domains\Crm\Models\ClientThreadMessage;
use App\Domains\Crm\Services\ClientThreadService;
use App\Domains\Crm\Services\MemberPortalAuthService;
use App\Domains\Crm\Services\NextVisitSchedulingService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NextVisitMemberController extends Controller
{
    public function __construct(
        private readonly MemberPortalAuthService $portal,
        private readonly NextVisitSchedulingService $scheduling,
        private readonly ClientThreadService $threads,
    ) {}

    public function upcoming(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $items = $this->scheduling->listForClient($client);

        return ApiResponse::success(
            AppointmentResource::collection($items)->resolve(),
        );
    }

    public function schedule(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $data = $request->validate([
            'visit_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'team_member_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.booking_service_id' => ['required', 'uuid'],
            'services.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $appointment = $this->scheduling->scheduleFromCheckIn(
            $client,
            $data['visit_id'],
            $data,
        );

        return ApiResponse::success(
            new AppointmentResource($appointment),
            'Next visit scheduled',
            201,
        );
    }

    public function threads(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $messages = $this->threads->listForClient($client);

        return ApiResponse::success(
            $messages->map(fn (ClientThreadMessage $m) => $this->threads->serialize($m))->values()->all(),
        );
    }

    private function requireClient(Request $request): \App\Domains\Crm\Models\Client
    {
        $token = $request->bearerToken();
        $client = $this->portal->findClientByToken($token);
        if ($client === null) {
            throw ValidationException::withMessages([
                'token' => ['Session expired. Please log in again.'],
            ]);
        }

        return $client;
    }
}
