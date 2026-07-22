<?php

namespace App\Domains\Notifications\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'notification_preferences';

    protected $attributes = [
        'allow_email' => true,
        'allow_sms' => true,
        'allow_whatsapp' => false,
        'allow_push' => false,
        'booking_notifications' => true,
        'payment_notifications' => true,
        'membership_notifications' => true,
        'general_notifications' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'client_id',
        'allow_email',
        'allow_sms',
        'allow_whatsapp',
        'allow_push',
        'booking_notifications',
        'payment_notifications',
        'membership_notifications',
        'general_notifications',
        'preferred_channel',
        'last_synced_from_consent_at',
    ];

    protected function casts(): array
    {
        return [
            'allow_email' => 'boolean',
            'allow_sms' => 'boolean',
            'allow_whatsapp' => 'boolean',
            'allow_push' => 'boolean',
            'booking_notifications' => 'boolean',
            'payment_notifications' => 'boolean',
            'membership_notifications' => 'boolean',
            'general_notifications' => 'boolean',
            'last_synced_from_consent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
