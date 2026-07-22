<?php

namespace App\Domains\Integrations\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderWebhookEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'provider_webhook_events';

    protected $fillable = [
        'tenant_id',
        'provider_account_id',
        'category',
        'driver',
        'event_type',
        'external_event_id',
        'received_at',
        'processed_at',
        'processing_status',
        'processing_error',
        'signature_valid',
        'payload_json',
        'headers_json',
        'resolved_source_domain',
        'resolved_source_type',
        'resolved_source_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'headers_json' => 'array',
            'metadata_json' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class, 'provider_account_id');
    }
}
