<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientNoteResource;
use App\Domains\Crm\Services\ClientNoteService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNoteController extends Controller
{
    public function __construct(
        private readonly ClientNoteService $noteService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $notes = $this->noteService->listForClient($this->clientService->find($clientId));

        return ApiResponse::success(ClientNoteResource::collection($notes));
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'note_type' => ['nullable', 'string', 'max:50'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $note = $this->noteService->create(
            $this->clientService->find($clientId),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new ClientNoteResource($note), 'Note added', 201);
    }
}
