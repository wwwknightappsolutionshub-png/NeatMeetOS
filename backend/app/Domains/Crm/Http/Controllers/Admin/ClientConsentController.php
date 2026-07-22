<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientConsentResource;
use App\Domains\Crm\Services\ClientConsentService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientConsentController extends Controller
{
    public function __construct(
        private readonly ClientConsentService $consentService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $client = $this->clientService->find($clientId);

        return ApiResponse::success([
            'current' => $this->consentService->currentState($client),
            'history' => ClientConsentResource::collection(
                $this->consentService->listForClient($client),
            ),
        ]);
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'consent_type' => ['required', 'string', 'max:100'],
            'granted' => ['required', 'boolean'],
            'source' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $record = $this->consentService->record(
            $this->clientService->find($clientId),
            $data,
        );

        return ApiResponse::success(new ClientConsentResource($record), 'Consent recorded', 201);
    }
}
