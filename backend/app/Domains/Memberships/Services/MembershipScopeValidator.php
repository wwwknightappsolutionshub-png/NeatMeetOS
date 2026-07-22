<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Booking\Services\BookingScopeValidator;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Memberships\Models\ClientLoyaltyEntry;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Memberships\Models\ClientPackage;
use App\Domains\Memberships\Models\ClientPackageRedemption;
use App\Domains\Memberships\Models\ClientWalletEntry;
use App\Domains\Memberships\Models\MembershipPlan;
use App\Domains\Memberships\Models\PackageProduct;

class MembershipScopeValidator
{
    public function __construct(private readonly BookingScopeValidator $bookingScope) {}

    public function tenantId(): string
    {
        return $this->bookingScope->tenantId();
    }

    public function assertTenantModel(object $model): void
    {
        $this->bookingScope->assertTenantModel($model);
    }

    public function findClient(string $id): Client
    {
        return $this->bookingScope->findClient($id);
    }

    public function findLocation(string $id): Location
    {
        return $this->bookingScope->findLocation($id);
    }

    public function findBookableService(string $id)
    {
        return $this->bookingScope->findBookableService($id);
    }

    public function findPlan(string $id): MembershipPlan
    {
        $plan = MembershipPlan::query()->with('locations')->findOrFail($id);
        $this->assertTenantModel($plan);

        return $plan;
    }

    public function findPackageProduct(string $id): PackageProduct
    {
        $product = PackageProduct::query()->with('bookingServices')->findOrFail($id);
        $this->assertTenantModel($product);

        return $product;
    }

    public function findClientMembership(string $id): ClientMembership
    {
        $membership = ClientMembership::query()->with(['client', 'membershipPlan'])->findOrFail($id);
        $this->assertTenantModel($membership);

        return $membership;
    }

    public function findClientPackage(string $id): ClientPackage
    {
        $package = ClientPackage::query()->with(['client', 'packageProduct', 'redemptions'])->findOrFail($id);
        $this->assertTenantModel($package);

        return $package;
    }

    public function findWalletEntry(string $id): ClientWalletEntry
    {
        $entry = ClientWalletEntry::query()->findOrFail($id);
        $this->assertTenantModel($entry);

        return $entry;
    }

    public function findLoyaltyEntry(string $id): ClientLoyaltyEntry
    {
        $entry = ClientLoyaltyEntry::query()->findOrFail($id);
        $this->assertTenantModel($entry);

        return $entry;
    }

    public function findPackageRedemption(string $id): ClientPackageRedemption
    {
        $redemption = ClientPackageRedemption::query()->findOrFail($id);
        $this->assertTenantModel($redemption);

        return $redemption;
    }
}
