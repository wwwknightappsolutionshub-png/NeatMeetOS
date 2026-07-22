<?php

namespace App\Domains\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const WORKSPACE_PROVISIONAL = 'provisional';

    public const WORKSPACE_COMPLETE = 'complete';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_platform_admin',
        'workspace_status',
        'signup_meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'signup_meta' => 'array',
        ];
    }

    public function needsWorkspace(): bool
    {
        return $this->workspace_status === self::WORKSPACE_PROVISIONAL;
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function currentTeamMember(): HasOne
    {
        return $this->hasOne(TeamMember::class)->where('is_active', true)->latestOfMany();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
