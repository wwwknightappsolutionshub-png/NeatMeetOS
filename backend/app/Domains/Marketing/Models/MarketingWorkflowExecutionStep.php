<?php

namespace App\Domains\Marketing\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingWorkflowExecutionStep extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'workflow_execution_id',
        'workflow_step_id',
        'position',
        'step_type',
        'status',
        'scheduled_for',
        'processed_at',
        'failure_reason',
        'message_id',
        'context_json',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'scheduled_for' => 'datetime',
            'processed_at' => 'datetime',
            'context_json' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(MarketingWorkflowExecution::class, 'workflow_execution_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(MarketingWorkflowStep::class, 'workflow_step_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MarketingMessage::class, 'message_id');
    }
}
