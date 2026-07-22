<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientPhotoResource;
use App\Domains\Crm\Services\ClientPhotoService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPhotoController extends Controller
{
    public function __construct(
        private readonly ClientPhotoService $photoService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $clientId): JsonResponse
    {
        $photos = $this->photoService->listForClient($this->clientService->find($clientId));

        return ApiResponse::success(ClientPhotoResource::collection($photos));
    }

    public function store(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'storage_path' => ['required', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $photo = $this->photoService->register(
            $this->clientService->find($clientId),
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new ClientPhotoResource($photo), 'Photo registered', 201);
    }

    public function archive(string $clientId, string $id): JsonResponse
    {
        $photo = \App\Domains\Crm\Models\ClientPhoto::query()->findOrFail($id);
        abort_if($photo->client_id !== $clientId, 404);

        $photo = $this->photoService->archive($photo);

        return ApiResponse::success(new ClientPhotoResource($photo), 'Photo archived');
    }
}
