<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Crm\Models\Client;
use App\Domains\Payments\Models\PaymentTransaction;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDeliveryAttempt extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'provider_delivery_attempts';

    protected $fillable = [
        'tenant_id',
        'provider_account_id',
        'category',
        'source_domain',
        'source_type',
        'source_id',
        'related_client_id',
        'related_appointment_id',
        'related_payment_transaction_id',
        'direction',
        'purpose',
        'recipient_address',
        'recipient_phone',
        'subject',
        'payload_json',
        'provider_reference',
        'idempotency_key',
        'status',
        'failure_code',
        'failure_message',
        'requested_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'metadata_json' => 'array',
            'requested_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class, 'provider_account_id');
    }

    public function relatedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'related_client_id');
    }

    public function relatedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'related_appointment_id');
    }

    public function relatedPaymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'related_payment_transaction_id');
    }
}
