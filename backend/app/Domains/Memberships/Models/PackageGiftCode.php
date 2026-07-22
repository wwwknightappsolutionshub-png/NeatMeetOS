<?php

namespace App\Domains\Memberships\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageGiftCode extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'package_product_id',
        'from_client_id',
        'from_client_package_id',
        'claimed_by_client_id',
        'claimed_client_package_id',
        'code',
        'status',
        'quantity',
        'recipient_name',
        'recipient_email',
        'expires_at',
        'claimed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function packageProduct(): BelongsTo
    {
        return $this->belongsTo(PackageProduct::class);
    }

    public function fromClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'from_client_id');
    }

    public function fromClientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class, 'from_client_package_id');
    }

    public function claimedByClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'claimed_by_client_id');
    }

    public function claimedClientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class, 'claimed_client_package_id');
    }
}
