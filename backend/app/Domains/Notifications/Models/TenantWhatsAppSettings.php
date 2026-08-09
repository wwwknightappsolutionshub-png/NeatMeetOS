<?php

namespace App\Domains\Notifications\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TenantWhatsAppSettings extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_PENDING_SCAN = 'pending_scan';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'tenant_whatsapp_settings';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'provider',
        'hosted_session_id',
        'hosted_phone_number',
        'hosted_status',
        'hosted_qr_payload',
        'hosted_connected_at',
        'hosted_last_seen_at',
        'hosted_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'hosted_connected_at' => 'datetime',
            'hosted_last_seen_at' => 'datetime',
            'hosted_expires_at' => 'datetime',
        ];
    }

    public function isHostedActive(): bool
    {
        return $this->enabled
            && $this->provider === 'genius'
            && $this->hosted_status === self::STATUS_ACTIVE
            && filled($this->hosted_session_id)
            && ($this->hosted_expires_at === null || $this->hosted_expires_at->isFuture());
    }
}
