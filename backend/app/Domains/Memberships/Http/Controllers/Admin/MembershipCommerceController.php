<?php

namespace App\Domains\Memberships\Http\Controllers\Admin;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Memberships\Http\Resources\ClientPackageResource;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Domains\Memberships\Services\LoyaltyRedemptionSettingsService;
use App\Domains\Memberships\Services\MembershipScopeValidator;
use App\Domains\Memberships\Services\PackageEntitlementService;
use App\Domains\Memberships\Services\WalletLedgerService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipCommerceController extends Controller
{
    public function __construct(
        private readonly PackageEntitlementService $packageEntitlement,
        private readonly WalletLedgerService $walletService,
        private readonly LoyaltyLedgerService $loyaltyService,
        private readonly LoyaltyRedemptionSettingsService $loyaltySettings,
        private readonly MembershipScopeValidator $scope,
    ) {}

    public function eligiblePackages(Request $request, string $clientId): JsonResponse
    {
        $this->scope->findClient($clientId);
        $filters = $request->validate([
            'booking_service_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
        ]);

        $packages = $this->packageEntitlement->listEligibleForClient(
            $clientId,
            $filters['booking_service_id'] ?? null,
        );

        return ApiResponse::success(ClientPackageResource::collection($packages));
    }

    public function walletSummary(string $clientId): JsonResponse
    {
        $this->scope->findClient($clientId);

        return ApiResponse::success([
            'client_id' => $clientId,
            'balance_cents' => $this->walletService->balanceForClient($clientId),
        ]);
    }

    public function loyaltySummary(string $clientId): JsonResponse
    {
        $this->scope->findClient($clientId);
        $balance = $this->loyaltyService->balanceForClient($clientId);
        $setting = $this->loyaltySettings->get();

        return ApiResponse::success([
            'client_id' => $clientId,
            'points_balance' => $balance,
            'redeemable_points' => $this->loyaltySettings->maxRedeemablePoints($balance),
            'redeemable_value_cents' => $this->loyaltySettings->redeemableValueCents($balance),
            'redemption_rule' => [
                'is_enabled' => $setting->is_loyalty_redemption_enabled,
                'points_per_block' => $setting->points_per_redemption_block,
                'value_cents_per_block' => $setting->value_cents_per_block,
            ],
        ]);
    }

    public function showLoyaltySettings(): JsonResponse
    {
        $setting = $this->loyaltySettings->get();

        return ApiResponse::success([
            'is_loyalty_redemption_enabled' => $setting->is_loyalty_redemption_enabled,
            'points_per_redemption_block' => $setting->points_per_redemption_block,
            'value_cents_per_block' => $setting->value_cents_per_block,
            'crm_join_signup_points' => $setting->crm_join_signup_points
                ?? \App\Domains\Memberships\Models\MembershipLoyaltySetting::DEFAULT_CRM_JOIN_SIGNUP_POINTS,
        ]);
    }

    public function updateLoyaltySettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_loyalty_redemption_enabled' => ['sometimes', 'boolean'],
            'points_per_redemption_block' => ['sometimes', 'integer', 'min:1'],
            'value_cents_per_block' => ['sometimes', 'integer', 'min:1'],
            'crm_join_signup_points' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $setting = $this->loyaltySettings->update($data);

        return ApiResponse::success([
            'is_loyalty_redemption_enabled' => $setting->is_loyalty_redemption_enabled,
            'points_per_redemption_block' => $setting->points_per_redemption_block,
            'value_cents_per_block' => $setting->value_cents_per_block,
            'crm_join_signup_points' => $setting->crm_join_signup_points
                ?? \App\Domains\Memberships\Models\MembershipLoyaltySetting::DEFAULT_CRM_JOIN_SIGNUP_POINTS,
        ]);
    }
}
