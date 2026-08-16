<?php

namespace App\Domains\Booking\Http\Controllers\Admin;

use App\Domains\Booking\Services\ReservationPaymentDocumentService;
use App\Domains\Identity\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationPaymentDocumentController extends Controller
{
    public function __construct(
        private readonly ReservationPaymentDocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending_review', 'confirmed', 'rejected'])],
        ]);

        $items = $this->documents->list($filters)->map(
            fn ($doc) => $this->documents->serialize($doc)
        )->values();

        return ApiResponse::success(['items' => $items]);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->documents->serialize($this->documents->find($id)));
    }

    public function confirm(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $doc = $this->documents->confirm($id, $teamMember?->id, $data['note'] ?? null);

        return ApiResponse::success($this->documents->serialize($doc), 'Payment document confirmed');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $teamMember = $request->attributes->get('team_member');
        $doc = $this->documents->reject($id, $teamMember?->id, $data['note'] ?? null);

        return ApiResponse::success($this->documents->serialize($doc), 'Payment document rejected');
    }
}
