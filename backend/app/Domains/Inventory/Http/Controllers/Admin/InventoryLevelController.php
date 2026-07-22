<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Http\Resources\InventoryLevelResource;
use App\Domains\Inventory\Services\InventoryLevelService;
use App\Domains\Inventory\Services\InventoryScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryLevelController extends Controller
{
    public function __construct(
        private readonly InventoryLevelService $levelService,
        private readonly InventoryScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'uuid'],
            'item_type' => ['nullable', Rule::in(InventoryItemType::all())],
            'low_stock' => ['nullable', 'boolean'],
        ]);

        if (isset($filters['low_stock'])) {
            $filters['low_stock'] = $request->boolean('low_stock');
        }

        return ApiResponse::success(
            InventoryLevelResource::collection($this->levelService->list($filters)),
        );
    }

    public function forItem(string $itemId): JsonResponse
    {
        return ApiResponse::success(
            InventoryLevelResource::collection($this->levelService->forItem($itemId)),
        );
    }

    public function update(Request $request, string $itemId, string $locationId): JsonResponse
    {
        $data = $request->validate([
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'reorder_target' => ['nullable', 'numeric', 'min:0'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $level = $this->levelService->updateForLocation(
            $this->scope->findItem($itemId),
            $locationId,
            $data,
            $teamMember?->id,
        );

        return ApiResponse::success(new InventoryLevelResource($level), 'Stock level updated');
    }
}
