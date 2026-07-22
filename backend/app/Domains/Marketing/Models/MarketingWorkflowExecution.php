<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Crm\Models\Client;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingWorkflowExecution extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'client_id',
        'campaign_id',
        'trigger_type',
        'trigger_reference_type',
        'trigger_reference_id',
        'status',
        'current_step_position',
        'scheduled_for',
        'started_at',
        'completed_at',
        'cancelled_at',
        'failure_reason',
        'context_json',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'current_step_position' => 'integer',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'context_json' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(MarketingAutomationWorkflow::class, 'workflow_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(MarketingWorkflowExecutionStep::class, 'workflow_execution_id')->orderBy('position');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingMessage::class, 'workflow_execution_id');
    }
}
