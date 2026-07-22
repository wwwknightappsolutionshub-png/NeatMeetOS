<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNote extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_GENERAL = 'general';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPE_INTERNAL = 'internal';

    public static function types(): array
    {
        return [self::TYPE_GENERAL, self::TYPE_FOLLOW_UP, self::TYPE_INTERNAL];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'author_team_member_id',
        'note_type',
        'body',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'author_team_member_id');
    }
}
