<?php

namespace App\Domains\Marketing\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMessageAttempt extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'marketing_message_id',
        'status',
        'attempted_at',
        'provider',
        'provider_reference',
        'provider_message_id',
        'payload_json',
        'response_json',
        'error_message',
        'failure_category',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'payload_json' => 'array',
            'response_json' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MarketingMessage::class, 'marketing_message_id');
    }
}
