<?php

namespace App\Domains\Crm\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNotice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_MARKETING_IN_APP = 'marketing.in_app';

    public const TYPE_OPERATIONAL_IN_APP = 'notification.in_app';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'marketing_message_id',
        'type',
        'title',
        'body',
        'href',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
