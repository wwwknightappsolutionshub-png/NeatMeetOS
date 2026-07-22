<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformNotification extends Model
{
    use HasUuid;

    public const TYPE_TENANT_SIGNUP = 'tenant.signup';

    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PlatformNotificationRead::class);
    }
}
