<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformUpgradeTemplate extends Model
{
    use HasUuid;

    public const PATH_BASIC_TO_PRO = 'basic_to_pro';

    public const PATH_PRO_TO_DIAMOND = 'pro_to_diamond';

    public const STEP_DAY_3 = 'day_3';

    public const STEP_DAY_7 = 'day_7';

    public const STEP_DAY_21 = 'day_21';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_IN_APP = 'in_app';

    protected $fillable = [
        'path',
        'step',
        'channel',
        'subject',
        'headline',
        'body_html',
        'body_text',
        'cta_label',
        'image_path',
        'features',
        'use_cases',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'use_cases' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
