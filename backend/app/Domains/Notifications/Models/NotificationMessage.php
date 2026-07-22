<?php

namespace App\Domains\Notifications\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Marketing\Models\MarketingWorkflowExecution;
use App\Domains\Memberships\Models\ClientMembership;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMessage extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'notifications_messages';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'appointment_id',
        'checkout_id',
        'payment_transaction_id',
        'client_membership_id',
        'marketing_workflow_execution_id',
        'notification_template_id',
        'source_type',
        'purpose',
        'channel',
        'direction',
        'status',
        'recipient_name',
        'recipient_address',
        'subject',
        'body_text',
        'body_html',
        'metadata',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'cancelled_at',
        'failure_reason',
        'created_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function clientMembership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class);
    }

    public function marketingWorkflowExecution(): BelongsTo
    {
        return $this->belongsTo(MarketingWorkflowExecution::class, 'marketing_workflow_execution_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationMessageAttempt::class, 'notification_message_id');
    }
}
