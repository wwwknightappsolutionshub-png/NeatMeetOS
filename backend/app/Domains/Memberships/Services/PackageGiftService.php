<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Memberships\Enums\ClientPackageSource;
use App\Domains\Memberships\Enums\ClientPackageStatus;
use App\Domains\Memberships\Enums\PackageGiftCodeStatus;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\PackageGiftCode;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageGiftService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly ClientPackageService $packages,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Gift remaining quantity from an owned package as a claimable code.
     *
     * @param  array{client_package_id: string, quantity?: float|int, recipient_name?: string, recipient_email?: string, notes?: string}  $data
     */
    public function createFromOwnedPackage(Client $fromClient, array $data): PackageGiftCode
    {
        $package = $this->scope->findClientPackage($data['client_package_id']);
        if ($package->client_id !== $fromClient->id) {
            throw ValidationException::withMessages([
                'client_package_id' => ['You can only gift packages you own.'],
            ]);
        }

        if ($package->status !== ClientPackageStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'client_package_id' => ['Only active packages can be gifted.'],
            ]);
        }

        $quantity = (float) ($data['quantity'] ?? 1);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be greater than zero.']]);
        }
        if ((float) $package->quantity_remaining < $quantity) {
            throw ValidationException::withMessages(['quantity' => ['Insufficient remaining quantity to gift.']]);
        }

        return DB::transaction(function () use ($package, $fromClient, $quantity, $data) {
            $package->quantity_remaining = (float) $package->quantity_remaining - $quantity;
            if ((float) $package->quantity_remaining <= 0) {
                $package->quantity_remaining = 0;
                $package->status = ClientPackageStatus::DEPLETED;
            }
            $package->save();

            $gift = PackageGiftCode::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'package_product_id' => $package->package_product_id,
                'from_client_id' => $fromClient->id,
                'from_client_package_id' => $package->id,
                'code' => $this->generateCode(),
                'status' => PackageGiftCodeStatus::OPEN,
                'quantity' => $quantity,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'expires_at' => now()->addDays(90),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->log('package_gift.created', $gift, null, [
                'from_client_id' => $fromClient->id,
                'quantity' => $quantity,
                'code' => $gift->code,
            ]);

            return $gift->fresh()->load(['packageProduct']);
        });
    }

    public function claim(Client $claimer, string $code): PackageGiftCode
    {
        $normalized = strtoupper(trim($code));
        $gift = PackageGiftCode::query()
            ->where('code', $normalized)
            ->first();

        if ($gift === null) {
            throw ValidationException::withMessages(['code' => ['Gift code not found.']]);
        }

        $this->scope->assertTenantModel($gift);

        if ($gift->status !== PackageGiftCodeStatus::OPEN) {
            throw ValidationException::withMessages(['code' => ['This gift code is no longer available.']]);
        }

        if ($gift->expires_at !== null && $gift->expires_at->isPast()) {
            $gift->status = PackageGiftCodeStatus::EXPIRED;
            $gift->save();
            throw ValidationException::withMessages(['code' => ['This gift code has expired.']]);
        }

        if ($gift->from_client_id === $claimer->id) {
            throw ValidationException::withMessages(['code' => ['You cannot claim your own gift code.']]);
        }

        return DB::transaction(function () use ($gift, $claimer) {
            $assigned = $this->packages->assign([
                'client_id' => $claimer->id,
                'package_product_id' => $gift->package_product_id,
                'quantity_total' => (float) $gift->quantity,
                'source' => ClientPackageSource::GIFT,
                'notes' => 'Claimed gift code '.$gift->code,
            ]);

            $gift->status = PackageGiftCodeStatus::CLAIMED;
            $gift->claimed_by_client_id = $claimer->id;
            $gift->claimed_client_package_id = $assigned->id;
            $gift->claimed_at = now();
            $gift->save();

            $this->auditLogger->log('package_gift.claimed', $gift, null, [
                'claimed_by_client_id' => $claimer->id,
                'client_package_id' => $assigned->id,
            ]);

            return $gift->fresh()->load(['packageProduct', 'claimedClientPackage']);
        });
    }

    /**
     * @return list<PackageGiftCode>
     */
    public function listForClient(Client $client): array
    {
        return PackageGiftCode::query()
            ->with('packageProduct')
            ->where(function ($q) use ($client) {
                $q->where('from_client_id', $client->id)
                    ->orWhere('claimed_by_client_id', $client->id);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->all();
    }

    private function generateCode(): string
    {
        do {
            $code = 'GIFT-'.Str::upper(Str::random(8));
        } while (PackageGiftCode::query()->where('code', $code)->exists());

        return $code;
    }
}
