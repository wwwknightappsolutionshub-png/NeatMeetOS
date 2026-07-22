<?php

namespace App\Domains\Payments\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Payments\Enums\PaymentDirection;
use App\Domains\Payments\Enums\PaymentProvider;
use App\Domains\Payments\Enums\PaymentTransactionStatus;
use App\Domains\Payments\Enums\PaymentTransactionType;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'client_id',
        'appointment_id',
        'team_member_id',
        'transaction_type',
        'direction',
        'status',
        'amount_cents',
        'currency',
        'provider',
        'provider_reference',
        'external_reference',
        'idempotency_key',
        'payment_method_type',
        'payment_method_label',
        'processed_at',
        'failed_at',
        'failure_code',
        'failure_message',
        'metadata',
        'created_by_team_member_id',
        'updated_by_team_member_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_team_member_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function refundableAmountCents(): int
    {
        if ($this->status !== PaymentTransactionStatus::SUCCEEDED) {
            return 0;
        }

        $refunded = $this->refunds()
            ->whereIn('status', ['succeeded', 'pending'])
            ->sum('amount_cents');

        return max(0, $this->amount_cents - $refunded);
    }
}
