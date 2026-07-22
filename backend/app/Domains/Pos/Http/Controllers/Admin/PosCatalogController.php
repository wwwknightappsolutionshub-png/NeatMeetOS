<?php

namespace App\Domains\Pos\Http\Controllers\Admin;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Inventory\Enums\InventoryItemStatus;
use App\Domains\Inventory\Enums\InventoryItemType;
use App\Domains\Inventory\Models\InventoryItem;
use App\Shared\Commerce\Assemblers\BookingCheckoutImportAssembler;
use App\Shared\Commerce\Enums\BillingSettlementStatus;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosCatalogController extends Controller
{
    public function __construct(
        private readonly BookingCheckoutImportAssembler $importAssembler,
    ) {}

    public function services(Request $request): JsonResponse
    {
        $services = BookableService::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price_cents', 'deposit_required']);

        return ApiResponse::success($services);
    }

    public function retail(Request $request): JsonResponse
    {
        $items = InventoryItem::query()
            ->where('status', InventoryItemStatus::ACTIVE)
            ->where('item_type', InventoryItemType::RETAIL)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'retail_price_cents']);

        return ApiResponse::success($items);
    }

    public function eligibleAppointments(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
        ]);

        $query = Appointment::query()
            ->with(['client', 'serviceLines'])
            ->whereIn('status', ['checked_in', 'completed'])
            ->where('billing_settlement_status', '!=', BillingSettlementStatus::SETTLED)
            ->orderByDesc('starts_at');

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        $appointments = $query->limit(50)->get()->map(function (Appointment $appointment) {
            $import = $this->importAssembler->assemble($appointment);

            return [
                'id' => $appointment->id,
                'booking_reference' => $appointment->booking_reference,
                'client_name' => $appointment->client
                    ? trim($appointment->client->first_name.' '.$appointment->client->last_name)
                    : null,
                'status' => $appointment->status,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'service_count' => $appointment->serviceLines->count(),
                'import_subtotal_cents' => collect($import->lines)->sum(fn ($l) => $l->lineTotalCents),
                'checkout_eligible' => $import->checkoutEligible,
                'ineligibility_reason' => $import->ineligibilityReason,
                'deposit_available_cents' => $import->deposit->collectedCents,
            ];
        });

        return ApiResponse::success($appointments);
    }
}
