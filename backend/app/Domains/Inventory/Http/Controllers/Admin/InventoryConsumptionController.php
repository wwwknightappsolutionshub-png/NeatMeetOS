<?php

namespace App\Domains\Inventory\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Services\InventoryConsumptionService;
use App\Shared\Commerce\Enums\InventoryConsumptionType;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryConsumptionController extends Controller
{
    public function __construct(private readonly InventoryConsumptionService $consumptionService) {}

    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'requests' => ['required', 'array', 'min:1'],
            'requests.*.checkout_id' => ['required', 'uuid'],
            'requests.*.checkout_line_id' => ['required', 'uuid'],
            'requests.*.consumption_type' => ['required', Rule::in(InventoryConsumptionType::all())],
            'requests.*.product_id' => ['required', 'uuid'],
            'requests.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'requests.*.location_id' => ['required', 'uuid'],
            'requests.*.appointment_service_line_id' => ['nullable', 'uuid'],
            'requests.*.recipe_snapshot' => ['nullable', 'array'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $result = $this->consumptionService->executeFromPayload($data, $teamMember?->id);

        return ApiResponse::success($result, 'Consumption processed', 201);
    }
}
