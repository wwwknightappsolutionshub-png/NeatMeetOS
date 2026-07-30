<?php

namespace App\Domains\AiHairstyle\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformAiHairstyleSetting extends Model
{
    use HasUuid;

    public const PROVIDER_STUB = 'stub';

    public const PROVIDER_REPLICATE = 'replicate';

    protected $fillable = [
        'provider',
    ];

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return [
            self::PROVIDER_STUB,
            self::PROVIDER_REPLICATE,
        ];
    }
}
