<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientPackage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'package_product_id',
        'status',
        'source',
        'purchased_at',
        'starts_at',
        'expires_at',
        'quantity_total',
        'quantity_remaining',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'quantity_total' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(ClientPackageRedemption::class);
    }
}
