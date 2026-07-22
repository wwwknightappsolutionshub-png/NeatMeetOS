<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPhoto extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CATEGORY_PROFILE = 'profile';

    public const CATEGORY_REFERENCE = 'reference';

    public const CATEGORY_FORMULA_REFERENCE = 'formula_reference';

    public const CATEGORY_HISTORY = 'history';

    public static function categories(): array
    {
        return [
            self::CATEGORY_PROFILE,
            self::CATEGORY_REFERENCE,
            self::CATEGORY_FORMULA_REFERENCE,
            self::CATEGORY_HISTORY,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'storage_path',
        'category',
        'caption',
        'uploaded_by_team_member_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'uploaded_by_team_member_id');
    }
}
