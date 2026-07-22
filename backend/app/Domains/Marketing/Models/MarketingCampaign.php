<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'campaign_type',
        'trigger_type',
        'channel',
        'status',
        'template_id',
        'audience_name',
        'audience_rules_json',
        'location_id',
        'created_by_team_member_id',
        'notes',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_rules_json' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketingTemplate::class, 'template_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MarketingRun::class, 'marketing_campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingMessage::class, 'marketing_campaign_id');
    }
}
