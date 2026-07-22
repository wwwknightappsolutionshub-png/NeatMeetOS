<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientResource;
use App\Domains\Crm\Http\Resources\ClientTagResource;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\ClientTagService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTagController extends Controller
{
    public function __construct(
        private readonly ClientTagService $tagService,
        private readonly ClientService $clientService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(ClientTagResource::collection($this->tagService->list()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        $tag = $this->tagService->create($data);

        return ApiResponse::success(new ClientTagResource($tag), 'Tag created', 201);
    }

    public function syncClientTags(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['uuid'],
        ]);

        $client = $this->tagService->syncClientTags(
            $this->clientService->find($clientId),
            $data['tag_ids'],
        );

        return ApiResponse::success(new ClientResource($client), 'Client tags updated');
    }
}
