<?php

namespace App\Domains\Lookbook\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LookbookItem extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'image_url',
        'title',
        'caption',
        'category_key',
        'sort_order',
        'is_published',
        'is_seeded',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'is_seeded' => 'boolean',
        ];
    }
}
