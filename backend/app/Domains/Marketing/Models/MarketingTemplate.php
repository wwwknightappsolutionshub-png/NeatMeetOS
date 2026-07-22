<?php

namespace App\Domains\Marketing\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingTemplate extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'channel',
        'subject',
        'body_text',
        'body_html',
        'variables_json',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables_json' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'template_id');
    }
}
