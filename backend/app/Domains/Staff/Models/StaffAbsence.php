<?php

namespace App\Domains\Staff\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAbsence extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CATEGORY_HOLIDAY = 'holiday';

    public const CATEGORY_SICKNESS = 'sickness';

    public const CATEGORY_UNAVAILABLE = 'unavailable';

    public const CATEGORY_TRAINING = 'training';

    public const CATEGORY_OTHER = 'other';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public static function categories(): array
    {
        return [
            self::CATEGORY_HOLIDAY,
            self::CATEGORY_SICKNESS,
            self::CATEGORY_UNAVAILABLE,
            self::CATEGORY_TRAINING,
            self::CATEGORY_OTHER,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'team_member_id',
        'category',
        'starts_at',
        'ends_at',
        'note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
