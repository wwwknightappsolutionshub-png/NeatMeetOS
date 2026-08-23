<?php

namespace App\Shared\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    use HasUuid;

    public const SOURCE_TURNSTILE = 'turnstile';

    public const SOURCE_THROTTLE = 'throttle';

    public const SOURCE_HONEYPOT = 'honeypot';

    public const SOURCE_LOGIN = 'login';

    protected $fillable = [
        'ip',
        'reason',
        'source',
        'banned_until',
        'hit_count',
    ];

    protected function casts(): array
    {
        return [
            'banned_until' => 'datetime',
            'hit_count' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        if ($this->banned_until === null) {
            return true;
        }

        return $this->banned_until->isFuture();
    }
}
