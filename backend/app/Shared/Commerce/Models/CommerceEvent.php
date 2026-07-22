<?php

namespace App\Shared\Commerce\Models;

use App\Shared\Commerce\Enums\CommerceEventName;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CommerceEvent extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'commerce_events';

    protected $fillable = [
        'tenant_id',
        'event_name',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'emitted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'emitted_at' => 'datetime',
        ];
    }

    public static function eventNames(): array
    {
        return CommerceEventName::all();
    }
}
