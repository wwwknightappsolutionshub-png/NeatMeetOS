<?php

namespace App\Domains\Marketing\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingWorkflowStep extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'position',
        'step_type',
        'delay_minutes',
        'template_id',
        'channel',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'delay_minutes' => 'integer',
            'payload_json' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(MarketingAutomationWorkflow::class, 'workflow_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketingTemplate::class, 'template_id');
    }
}
