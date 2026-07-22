<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientFormulaResource;
use App\Domains\Crm\Services\ClientFormulaService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientFormulaController extends Controller
{
    public function __construct(
        private readonly ClientFormulaService $formulaService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $formulas = $this->formulaService->listForClient($this->clientService->find($clientId));

        return ApiResponse::success(ClientFormulaResource::collection($formulas));
    }

    public function show(string $clientId, string $id): JsonResponse
    {
        $formula = $this->formulaService->find($id);
        abort_if($formula->client_id !== $clientId, 404);

        return ApiResponse::success(new ClientFormulaResource($formula->load('recordedBy')));
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'formula_body' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_context' => ['nullable', 'string', 'max:255'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $formula = $this->formulaService->create(
            $this->clientService->find($clientId),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new ClientFormulaResource($formula), 'Formula created', 201);
    }

    public function update(Request $request, string $clientId, string $id): JsonResponse
    {
        $formula = $this->formulaService->find($id);
        abort_if($formula->client_id !== $clientId, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'formula_body' => ['sometimes', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_context' => ['nullable', 'string', 'max:255'],
        ]);

        $formula = $this->formulaService->update($formula, $data);

        return ApiResponse::success(new ClientFormulaResource($formula), 'Formula updated');
    }

    public function archive(string $clientId, string $id): JsonResponse
    {
        $formula = $this->formulaService->find($id);
        abort_if($formula->client_id !== $clientId, 404);

        $formula = $this->formulaService->archive($formula);

        return ApiResponse::success(new ClientFormulaResource($formula), 'Formula archived');
    }
}
