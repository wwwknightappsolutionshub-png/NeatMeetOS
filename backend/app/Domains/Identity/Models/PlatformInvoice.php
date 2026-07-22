<?php

namespace App\Domains\Identity\Models;

use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformInvoice extends Model
{
    use HasUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_VOID = 'void';

    public const STATUS_UNCOLLECTIBLE = 'uncollectible';

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'subscription_plan_id',
        'invoice_number',
        'status',
        'currency',
        'amount_cents',
        'amount_paid_cents',
        'billing_interval',
        'period_start',
        'period_end',
        'due_at',
        'paid_at',
        'voided_at',
        'attempt_count',
        'next_attempt_at',
        'failure_reason',
        'line_items_json',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'line_items_json' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PlatformInvoiceAttempt::class);
    }
}
