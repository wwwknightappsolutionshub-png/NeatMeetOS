<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientResource;
use App\Domains\Crm\Services\ClientConsentService;
use App\Domains\Crm\Services\ClientDataPrivacyService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly ClientConsentService $consentService,
        private readonly ClientDataPrivacyService $privacy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable'],
            'primary_location_id' => ['nullable', 'uuid'],
            'tag_ids' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);

        $paginator = $this->clientService->list($filters, $perPage);

        return ApiResponse::success([
            'items' => ClientResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'special_event_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'special_event_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'special_event_label' => ['nullable', 'string', 'max:80'],
            'primary_location_id' => ['nullable', 'uuid'],
            'preferred_team_member_id' => ['nullable', 'uuid'],
            'internal_flags' => ['nullable', 'array'],
            'preferences' => ['nullable', 'array'],
            'loyalty_display_status' => ['nullable', 'string', 'max:100'],
        ]);

        $client = $this->clientService->create($data);

        return ApiResponse::success(new ClientResource($client), 'Client created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $client = $this->clientService->find($id);
        $resource = (new ClientResource($client))->toArray(request());
        $resource['communication_preferences'] = $this->consentService->currentState($client);

        return ApiResponse::success($resource);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'min:7', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'special_event_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'special_event_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'special_event_label' => ['nullable', 'string', 'max:80'],
            'primary_location_id' => ['nullable', 'uuid'],
            'preferred_team_member_id' => ['nullable', 'uuid'],
            'internal_flags' => ['nullable', 'array'],
            'preferences' => ['nullable', 'array'],
            'loyalty_display_status' => ['nullable', 'string', 'max:100'],
        ]);

        $client = $this->clientService->update(
            $this->clientService->find($id),
            $data,
        );

        return ApiResponse::success(new ClientResource($client), 'Client updated');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $client = $this->clientService->setActive(
            $this->clientService->find($id),
            $data['is_active'],
        );

        return ApiResponse::success(new ClientResource($client), 'Client status updated');
    }

    public function export(string $id): JsonResponse
    {
        return ApiResponse::success($this->privacy->export($id), 'Client data export');
    }

    public function erase(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ]);

        $client = $this->privacy->erase($id, $data['reason'] ?? null);

        return ApiResponse::success(new ClientResource($client), 'Client personal data erased');
    }
}

