<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAutomationWorkflow extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'trigger_type',
        'channel',
        'status',
        'audience_rules_json',
        'template_id',
        'delay_minutes',
        'cooldown_days',
        'allow_repeat',
        'max_executions_per_client',
        'settings_json',
        'created_by_team_member_id',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_rules_json' => 'array',
            'settings_json' => 'array',
            'delay_minutes' => 'integer',
            'cooldown_days' => 'integer',
            'max_executions_per_client' => 'integer',
            'allow_repeat' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketingTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MarketingWorkflowStep::class, 'workflow_id')->orderBy('position');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(MarketingWorkflowExecution::class, 'workflow_id');
    }
}
