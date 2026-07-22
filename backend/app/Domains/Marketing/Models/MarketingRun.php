<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingRun extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected static function booted(): void
    {
        static::created(function (MarketingRun $run): void {
            app(AuditLogger::class)->log('marketing_run.created', $run, null, [
                'marketing_campaign_id' => $run->marketing_campaign_id,
                'trigger_type' => $run->trigger_type,
                'run_source' => $run->run_source,
            ]);
        });
    }

    protected $fillable = [
        'tenant_id',
        'marketing_campaign_id',
        'trigger_type',
        'run_source',
        'status',
        'filters_json',
        'summary_json',
        'started_at',
        'completed_at',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'summary_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingMessage::class, 'marketing_run_id');
    }
}
