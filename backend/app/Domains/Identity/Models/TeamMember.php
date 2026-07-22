<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeamMember extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const EMPLOYMENT_OWNER = 'owner';

    public const EMPLOYMENT_EMPLOYEE = 'employee';

    public const EMPLOYMENT_FREELANCER = 'freelancer';

    public const EMPLOYMENT_CHAIR_RENTER = 'chair_renter';

    public const EMPLOYMENT_ROOM_RENTER = 'room_renter';

    public static function employmentTypes(): array
    {
        return [
            self::EMPLOYMENT_OWNER,
            self::EMPLOYMENT_EMPLOYEE,
            self::EMPLOYMENT_FREELANCER,
            self::EMPLOYMENT_CHAIR_RENTER,
            self::EMPLOYMENT_ROOM_RENTER,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'first_name',
        'last_name',
        'employment_type',
        'display_name',
        'phone',
        'primary_location_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'team_member_role');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'team_member_workspace');
    }

    public function staffProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\Staff\Models\StaffProfile::class);
    }

    public function operatingLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'staff_operating_locations');
    }

    public function availabilityRules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Staff\Models\StaffAvailabilityRule::class);
    }

    public function absences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Staff\Models\StaffAbsence::class);
    }
}
