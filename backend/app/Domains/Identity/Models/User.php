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
        'platform_role',
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

    public function platformRole(): ?string
    {
        return \App\Domains\Identity\Support\PlatformRole::effective(
            (bool) $this->is_platform_admin,
            $this->platform_role,
        );
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
        // Do not use latestOfMany()/ofMany() — PostgreSQL cannot aggregate uuid PKs,
        // and ofMany subqueries interact poorly with BelongsToTenant scopes.
        return $this->hasOne(TeamMember::class)
            ->where('is_active', true)
            ->orderByDesc('created_at');
    }

    public function resolveActiveTeamMember(): ?TeamMember
    {
        return TeamMember::withoutGlobalScopes()
            ->where('user_id', $this->id)
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->orderByDesc('created_at')
            ->first();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
