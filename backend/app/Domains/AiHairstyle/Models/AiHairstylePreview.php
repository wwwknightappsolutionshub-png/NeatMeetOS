<?php

namespace App\Domains\AiHairstyle\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiHairstylePreview extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'session_id',
        'status',
        'composite_image_url',
        'style_label',
        'style_key',
        'sort_order',
        'provider_meta',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'provider_meta' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiHairstyleSession::class, 'session_id');
    }
}
