<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientDocumentResource;
use App\Domains\Crm\Services\ClientDocumentService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDocumentController extends Controller
{
    public function __construct(
        private readonly ClientDocumentService $documentService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $documents = $this->documentService->listForClient($this->clientService->find($clientId));

        return ApiResponse::success(ClientDocumentResource::collection($documents));
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'storage_path' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $document = $this->documentService->register(
            $this->clientService->find($clientId),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new ClientDocumentResource($document), 'Document registered', 201);
    }

    public function archive(string $clientId, string $id): JsonResponse
    {
        $document = \App\Domains\Crm\Models\ClientDocument::query()->findOrFail($id);
        abort_if($document->client_id !== $clientId, 404);

        $document = $this->documentService->archive($document);

        return ApiResponse::success(new ClientDocumentResource($document), 'Document archived');
    }
}
