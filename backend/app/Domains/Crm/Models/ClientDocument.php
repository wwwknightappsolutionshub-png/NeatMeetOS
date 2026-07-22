<?php

namespace App\Domains\Crm\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPE_REFERENCE = 'reference';

    public const TYPE_SIGNED = 'signed';

    public const TYPE_PREFERENCE = 'preference';

    public const TYPE_OTHER = 'other';

    public static function types(): array
    {
        return [
            self::TYPE_REFERENCE,
            self::TYPE_SIGNED,
            self::TYPE_PREFERENCE,
            self::TYPE_OTHER,
        ];
    }

    protected $fillable = [
        'tenant_id',
        'client_id',
        'title',
        'document_type',
        'storage_path',
        'description',
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
