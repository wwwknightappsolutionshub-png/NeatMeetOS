<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Http\Resources\ClientWalletEntryResource;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Domains\Memberships\Services\WalletLedgerService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletLedgerController extends Controller
{
    public function __construct(
        private readonly WalletLedgerService $walletService,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'client_id' => ['nullable', 'uuid'],
        ]);

        return ApiResponse::success(ClientWalletEntryResource::collection($this->walletService->list($filters)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'direction' => ['required', 'in:credit,debit'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $teamMember = $request->attributes->get('team_member');

        $entry = $data['direction'] === 'credit'
            ? $this->walletService->postManualCredit($data, $teamMember?->id)
            : $this->walletService->postManualDebit($data, $teamMember?->id);

        return ApiResponse::success(new ClientWalletEntryResource($entry), 'Wallet entry created', 201);
    }

    public function clientBalance(string $clientId): JsonResponse
    {
        $this->scope->findClient($clientId);
        $balance = $this->walletService->balanceForClient($clientId);
        $entries = $this->walletService->list(['client_id' => $clientId]);

        return ApiResponse::success([
            'client_id' => $clientId,
            'balance_cents' => $balance,
            'entries' => ClientWalletEntryResource::collection($entries),
        ]);
    }
}
