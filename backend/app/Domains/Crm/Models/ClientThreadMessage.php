<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientThreadMessage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_WHATSAPP_MODE_A = 'whatsapp_mode_a';

    public const CHANNEL_EMAIL = 'email';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'author_user_id',
        'direction',
        'channel',
        'subject',
        'body',
        'whatsapp_deeplink',
        'metadata',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
