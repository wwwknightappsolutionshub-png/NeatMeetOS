<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFormula extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CATEGORY_COLOUR = 'colour';

    public const CATEGORY_TREATMENT = 'treatment';

    public const CATEGORY_PRODUCT_MIX = 'product_mix';

    public const CATEGORY_OTHER = 'other';

    public static function categories(): array
    {
        return [
            self::CATEGORY_COLOUR,
            self::CATEGORY_TREATMENT,
            self::CATEGORY_PRODUCT_MIX,
            self::CATEGORY_OTHER,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'title',
        'formula_body',
        'category',
        'service_context',
        'recorded_by_team_member_id',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'recorded_by_team_member_id');
    }
}
