<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Http\Resources\ClientLoyaltyEntryResource;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyLedgerController extends Controller
{
    public function __construct(
        private readonly LoyaltyLedgerService $loyaltyService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(ClientLoyaltyEntryResource::collection($this->loyaltyService->list($filters)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'direction' => ['required', 'in:credit,debit'],
            'points' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $entry = $data['direction'] === 'credit'
            ? $this->loyaltyService->postManualAward($data, $teamMember?->id)
            : $this->loyaltyService->postManualDeduction($data, $teamMember?->id);

        return ApiResponse::success(new ClientLoyaltyEntryResource($entry), 'Loyalty entry created', 201);
    }

    public function clientBalance(string $clientId): JsonResponse
    {
        $this->scope->findClient($clientId);
        $balance = $this->loyaltyService->balanceForClient($clientId);
        $entries = $this->loyaltyService->list(['client_id' => $clientId]);

        return ApiResponse::success([
            'client_id' => $clientId,
            'points_balance' => $balance,
            'entries' => ClientLoyaltyEntryResource::collection($entries),
        ]);
    }
}
