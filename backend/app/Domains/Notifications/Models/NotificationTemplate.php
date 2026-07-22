<?php

namespace App\Domains\Notifications\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'notifications_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'channel',
        'category',
        'subject',
        'body_text',
        'body_html',
        'variables_json',
        'is_system',
        'is_active',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'variables_json' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }
}
