<?php

namespace App\Domains\Memberships\Services;

use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Domains\Memberships\Models\PackageProduct;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageProductService
{
    public function __construct(
        private readonly MembershipScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = PackageProduct::query()->with('bookingServices')->orderBy('name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): PackageProduct
    {
        return $this->scope->findPackageProduct($id);
    }

    public function create(array $data): PackageProduct
    {
        $product = DB::transaction(function () use ($data) {
            $product = PackageProduct::query()->create([
                'tenant_id' => $this->scope->tenantId(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? MembershipPlanStatus::ACTIVE,
                'price_cents' => $data['price_cents'],
                'included_quantity' => $data['included_quantity'],
                'expiry_days' => $data['expiry_days'] ?? null,
                'is_public' => $data['is_public'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncServices($product, $data['service_restrictions'] ?? []);

            return $product;
        });

        $this->auditLogger->log('package_product.created', $product, null, $product->only(['name', 'price_cents', 'included_quantity']));

        return $product->fresh()->load('bookingServices');
    }

    public function update(PackageProduct $product, array $data): PackageProduct
    {
        $this->scope->assertTenantModel($product);
        $old = $product->only(['name', 'status', 'price_cents', 'included_quantity']);

        DB::transaction(function () use ($product, $data) {
            $product->fill(array_intersect_key($data, array_flip([
                'name', 'description', 'status', 'price_cents', 'included_quantity',
                'expiry_days', 'is_public', 'notes',
            ])));
            $product->save();

            if (array_key_exists('service_restrictions', $data)) {
                $this->syncServices($product, $data['service_restrictions']);
            }
        });

        $this->auditLogger->log('package_product.updated', $product, $old, $product->only(['name', 'status', 'price_cents', 'included_quantity']));

        return $product->fresh()->load('bookingServices');
    }

    public function archive(PackageProduct $product): PackageProduct
    {
        $this->scope->assertTenantModel($product);
        $product->status = MembershipPlanStatus::ARCHIVED;
        $product->archived_at = now();
        $product->save();

        $this->auditLogger->log('package_product.archived', $product, null, ['status' => MembershipPlanStatus::ARCHIVED]);

        return $product;
    }

    /**
     * @param  array<int, array{booking_service_id: string, quantity_per_redemption?: float}>  $restrictions
     */
    private function syncServices(PackageProduct $product, array $restrictions): void
    {
        $sync = [];
        foreach ($restrictions as $restriction) {
            $serviceId = $restriction['booking_service_id'];
            $this->scope->findBookableService($serviceId);
            $sync[$serviceId] = [
                'id' => (string) Str::uuid(),
                'quantity_per_redemption' => $restriction['quantity_per_redemption'] ?? 1,
            ];
        }

        $product->bookingServices()->sync($sync);
    }
}
