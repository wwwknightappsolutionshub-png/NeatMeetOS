<?php

namespace App\Domains\Notifications\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationMessageAttempt extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'notifications_message_attempts';

    protected $fillable = [
        'tenant_id',
        'notification_message_id',
        'attempt_number',
        'provider',
        'provider_reference',
        'status',
        'request_payload',
        'response_payload',
        'attempted_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'attempted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'notification_message_id');
    }
}
