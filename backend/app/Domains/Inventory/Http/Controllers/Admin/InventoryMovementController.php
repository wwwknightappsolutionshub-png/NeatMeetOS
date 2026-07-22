<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Enums\InventoryMovementType;
use App\Domains\Inventory\Enums\MovementReferenceType;
use App\Domains\Inventory\Http\Resources\InventoryMovementResource;
use App\Domains\Inventory\Services\InventoryMovementService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryMovementController extends Controller
{
    public function __construct(private readonly InventoryMovementService $movementService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'inventory_item_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'movement_type' => ['nullable', Rule::in(InventoryMovementType::all())],
            'from' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            InventoryMovementResource::collection($this->movementService->list($filters)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'uuid'],
            'location_id' => ['required', 'uuid'],
            'movement_type' => ['required', Rule::in(InventoryMovementType::manualTypes())],
            'quantity_delta' => ['required', 'numeric'],
            'unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'reference_type' => ['nullable', Rule::in(MovementReferenceType::all())],
            'reference_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
            'allow_negative' => ['sometimes', 'boolean'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $movement = $this->movementService->record($data, $teamMember?->id);

        return ApiResponse::success(new InventoryMovementResource($movement), 'Movement recorded', 201);
    }
}
