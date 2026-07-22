<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlatformSignupFormDefinition extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'steps',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
