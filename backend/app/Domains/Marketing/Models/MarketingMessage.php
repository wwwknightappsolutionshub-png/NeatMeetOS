<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Memberships\Models\ClientMembership;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMessage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'marketing_campaign_id',
        'marketing_run_id',
        'workflow_execution_id',
        'workflow_step_id',
        'client_id',
        'appointment_id',
        'membership_id',
        'location_id',
        'channel',
        'purpose',
        'status',
        'recipient_address',
        'subject',
        'rendered_body_text',
        'rendered_body_html',
        'template_snapshot_json',
        'variables_snapshot_json',
        'scheduled_for',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'unsubscribed_at',
        'failed_at',
        'suppressed_at',
        'skipped_reason',
        'provider_message_reference',
        'provider_message_id',
        'failure_category',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'template_snapshot_json' => 'array',
            'variables_snapshot_json' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'failed_at' => 'datetime',
            'suppressed_at' => 'datetime',
        ];
    }

    public function workflowExecution(): BelongsTo
    {
        return $this->belongsTo(MarketingWorkflowExecution::class, 'workflow_execution_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(MarketingWorkflowStep::class, 'workflow_step_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MarketingRun::class, 'marketing_run_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'membership_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MarketingMessageAttempt::class, 'marketing_message_id');
    }
}
