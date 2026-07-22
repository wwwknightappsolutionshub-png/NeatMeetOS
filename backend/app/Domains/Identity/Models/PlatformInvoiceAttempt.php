<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoiceAttempt extends Model
{
    use HasUuid;

    protected $fillable = [
        'platform_invoice_id',
        'tenant_id',
        'status',
        'provider',
        'provider_reference',
        'failure_code',
        'failure_message',
        'response_json',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
