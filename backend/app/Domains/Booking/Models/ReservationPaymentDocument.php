<?php

namespace App\Domains\Booking\Models;

use App\Domains\Crm\Models\Client;
use App\Domains\Identity\Models\TeamMember;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPaymentDocument extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_STRIPE = 'stripe';

    public const METHOD_GOOGLE_PAY = 'google_pay';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public static function methods(): array
    {
        return [
            self::METHOD_TRANSFER,
            self::METHOD_STRIPE,
            self::METHOD_GOOGLE_PAY,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_CONFIRMED,
            self::STATUS_REJECTED,
        ];
    }

    protected $table = 'booking_reservation_payment_documents';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'client_id',
        'booking_service_id',
        'amount_cents',
        'payment_method',
        'status',
        'proof_path',
        'proof_original_name',
        'proof_mime',
        'proof_size_bytes',
        'public_token',
        'reviewed_by_team_member_id',
        'reviewed_at',
        'review_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'proof_size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookableService::class, 'booking_service_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'reviewed_by_team_member_id');
    }
}
