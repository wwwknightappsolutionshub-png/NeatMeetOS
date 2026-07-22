<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Enums\PackageRedemptionType;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientPackageRedemption;
use App\Shared\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientPackageService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = ClientPackage::query()
            ->with(['client', 'packageProduct'])
            ->orderByDesc('purchased_at');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): ClientPackage
    {
        return $this->scope->findClientPackage($id);
    }

    public function assign(array $data, ?string $teamMemberId = null): ClientPackage
    {
        $client = $this->scope->findClient($data['client_id']);
        $product = $this->scope->findPackageProduct($data['package_product_id']);

        if ($product->status !== MembershipPlanStatus::ACTIVE) {
            throw ValidationException::withMessages(['package_product_id' => ['Package product is not active.']]);
        }

        $quantity = $data['quantity_total'] ?? $product->included_quantity;
        $purchasedAt = isset($data['purchased_at']) ? Carbon::parse($data['purchased_at']) : now();
        $expiresAt = null;

        if ($product->expiry_days !== null) {
            $expiresAt = $purchasedAt->copy()->addDays($product->expiry_days);
        } elseif (! empty($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at']);
        }

        $package = ClientPackage::query()->create([
            'tenant_id' => $this->scope->tenantId(),
            'client_id' => $client->id,
            'package_product_id' => $product->id,
            'status' => ClientPackageStatus::ACTIVE,
            'source' => $data['source'] ?? ClientPackageSource::MANUAL,
            'purchased_at' => $purchasedAt,
            'starts_at' => $data['starts_at'] ?? $purchasedAt,
            'expires_at' => $expiresAt,
            'quantity_total' => $quantity,
            'quantity_remaining' => $quantity,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditLogger->log('client_package.created', $package, null, $package->only(['client_id', 'package_product_id', 'quantity_total']));

        return $package->fresh()->load(['client', 'packageProduct']);
    }

    public function redeem(ClientPackage $package, array $data, ?string $teamMemberId = null): ClientPackage
    {
        $this->scope->assertTenantModel($package);
        $this->assertRedeemable($package);

        $quantity = (float) $data['quantity'];
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be greater than zero.']]);
        }

        if ((float) $package->quantity_remaining < $quantity) {
            throw ValidationException::withMessages(['quantity' => ['Insufficient package balance.']]);
        }

        DB::transaction(function () use ($package, $data, $quantity, $teamMemberId) {
            $package->quantity_remaining = (float) $package->quantity_remaining - $quantity;
            if ((float) $package->quantity_remaining <= 0) {
                $package->quantity_remaining = 0;
                $package->status = ClientPackageStatus::DEPLETED;
            }
            $package->save();

            ClientPackageRedemption::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_package_id' => $package->id,
                'client_id' => $package->client_id,
                'booking_service_id' => $data['booking_service_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'checkout_id' => $data['checkout_id'] ?? null,
                'redemption_type' => PackageRedemptionType::MANUAL_REDEEM,
                'quantity' => $quantity,
                'notes' => $data['notes'] ?? null,
                'created_by_team_member_id' => $teamMemberId,
            ]);
        });

        $this->auditLogger->log('client_package.redeemed', $package, null, [
            'quantity' => $quantity,
            'quantity_remaining' => $package->quantity_remaining,
        ]);

        return $package->fresh()->load(['client', 'packageProduct', 'redemptions']);
    }

    public function restore(ClientPackage $package, array $data, ?string $teamMemberId = null): ClientPackage
    {
        $this->scope->assertTenantModel($package);

        $quantity = (float) $data['quantity'];
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be greater than zero.']]);
        }

        $maxRestore = (float) $package->quantity_total - (float) $package->quantity_remaining;
        if ($quantity > $maxRestore) {
            throw ValidationException::withMessages(['quantity' => ['Cannot restore more than was redeemed.']]);
        }

        DB::transaction(function () use ($package, $data, $quantity, $teamMemberId) {
            $package->quantity_remaining = (float) $package->quantity_remaining + $quantity;
            if ($package->status === ClientPackageStatus::DEPLETED && (float) $package->quantity_remaining > 0) {
                $package->status = ClientPackageStatus::ACTIVE;
            }
            $package->save();

            ClientPackageRedemption::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'client_package_id' => $package->id,
                'client_id' => $package->client_id,
                'booking_service_id' => $data['booking_service_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'checkout_id' => $data['checkout_id'] ?? null,
                'redemption_type' => PackageRedemptionType::MANUAL_RESTORE,
                'quantity' => $quantity,
                'notes' => $data['notes'] ?? null,
                'created_by_team_member_id' => $teamMemberId,
            ]);
        });

        $this->auditLogger->log('client_package.restored', $package, null, [
            'quantity' => $quantity,
            'quantity_remaining' => $package->quantity_remaining,
        ]);

        return $package->fresh()->load(['client', 'packageProduct', 'redemptions']);
    }

    private function assertRedeemable(ClientPackage $package): void
    {
        if ($package->status !== ClientPackageStatus::ACTIVE) {
            throw ValidationException::withMessages(['status' => ['Package is not active.']]);
        }

        if ($package->expires_at !== null && $package->expires_at->isPast()) {
            throw ValidationException::withMessages(['expires_at' => ['Package has expired.']]);
        }
    }
}
