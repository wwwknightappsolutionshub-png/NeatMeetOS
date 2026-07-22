<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Services\AppointmentBookingService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Commerce\Contracts\CheckoutImportFromBookingContract;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AppointmentCommerceController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly CheckoutImportFromBookingContract $checkoutImport,
    ) {}

    /**
     * Contract inspection endpoint — maps appointment to checkout import DTO.
     * Full POS basket assembly is Module 8.
     */
    public function checkoutImport(string $id): JsonResponse
    {
        $appointment = $this->bookingService->find($id);
        $dto = $this->checkoutImport->import($appointment);

        return ApiResponse::success($dto->toArray());
    }
}
