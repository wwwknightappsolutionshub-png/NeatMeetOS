<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformUpgradeCampaignSetting extends Model
{
    use HasUuid;

    protected $fillable = [
        'is_enabled',
        'discount_percent',
        'channel_email',
        'channel_whatsapp',
        'channel_in_app',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'channel_email' => 'boolean',
            'channel_whatsapp' => 'boolean',
            'channel_in_app' => 'boolean',
            'discount_percent' => 'integer',
        ];
    }
}
