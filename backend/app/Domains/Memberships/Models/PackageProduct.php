<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Booking\Models\BookableService;
use App\Domains\Memberships\Enums\MembershipPlanStatus;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageProduct extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'price_cents',
        'included_quantity',
        'expiry_days',
        'is_public',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'included_quantity' => 'decimal:3',
            'expiry_days' => 'integer',
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function bookingServices(): BelongsToMany
    {
        return $this->belongsToMany(BookableService::class, 'package_product_services', 'package_product_id', 'booking_service_id')
            ->withPivot(['id', 'quantity_per_redemption'])
            ->withTimestamps();
    }

    public function clientPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipPlanStatus::ACTIVE;
    }
}
