<?php

namespace App\Domains\Crm\Http\Controllers\Admin;

use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\ClientVisitService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ClientVisitController extends Controller
{
    public function __construct(
        private readonly ClientVisitService $visitService,
        private readonly ClientService $clientService,
    ) {}

    public function index(string $id): JsonResponse
    {
        $this->clientService->find($id);
        $visits = $this->visitService->listForClient($id);

        return ApiResponse::success($visits->map(fn ($visit) => [
            'id' => $visit->id,
            'client_id' => $visit->client_id,
            'location_id' => $visit->location_id,
            'location' => $visit->location ? [
                'id' => $visit->location->id,
                'name' => $visit->location->name,
            ] : null,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
            'source' => $visit->source,
            'loyalty_points_awarded' => $visit->loyalty_points_awarded,
            'notes' => $visit->notes,
            'created_at' => $visit->created_at?->toIso8601String(),
        ])->all());
    }
}
