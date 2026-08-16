<?php

namespace App\Domains\Booking\Http\Controllers\PublicBooking;

use App\Domains\Booking\Services\ReservationPaymentDocumentService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicReservationPaymentController extends Controller
{
    public function __construct(
        private readonly ReservationPaymentDocumentService $documents,
    ) {}

    public function storeProof(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_service_id' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in(['transfer', 'stripe', 'google_pay'])],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $doc = $this->documents->createFromPublicUpload(
            $request->file('proof'),
            $data['booking_service_id'],
            $data['payment_method'],
        );

        return ApiResponse::success([
            'id' => $doc->id,
            'public_token' => $doc->public_token,
            'amount_cents' => $doc->amount_cents,
            'payment_method' => $doc->payment_method,
            'status' => $doc->status,
        ], 'Transfer proof uploaded', 201);
    }
}
