<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Http\Resources\ClientTimelineResource;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\ClientTimelineService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTimelineController extends Controller
{
    public function __construct(
        private readonly ClientTimelineService $timelineService,
        private readonly ClientService $clientService,
    ) {}

    public function index(Request $request, string $clientId): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->timelineService->listForClient(
            $this->clientService->find($clientId),
            (int) ($filters['per_page'] ?? 50),
        );

        return ApiResponse::success([
            'items' => ClientTimelineResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
