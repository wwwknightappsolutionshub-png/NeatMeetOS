<?php

namespace App\Domains\GrowthAssessment\Models;

use App\Domains\Identity\Models\User;
use App\Shared\Tenancy\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-tenant salon growth assessment lead.
 * Not tenant-scoped — never mixed into salon CRM clients.
 */
class SalonGrowthAssessment extends Model
{
    use HasUuid;

    public const LEAD_STATUSES = [
        'new',
        'contacted',
        'qualified',
        'demo_booked',
        'trial_started',
        'converted',
        'not_interested',
        'no_response',
    ];

    protected $fillable = [
        'public_token',
        'business_name',
        'business_type',
        'staff_band',
        'customers_per_month_band',
        'contact_name',
        'email',
        'phone',
        'phone_normalized',
        'postcode',
        'marketing_consent',
        'answers',
        'score_overall',
        'score_visibility',
        'score_retention',
        'score_revenue_visibility',
        'score_reengagement',
        'estimated_opportunity_cents',
        'primary_opportunity',
        'primary_opportunity_label',
        'sales_conversation_hint',
        'uses_software',
        'software_helps_with',
        'software_satisfaction',
        'tracking_methods',
        'lead_status',
        'assigned_platform_user_id',
        'internal_notes',
        'last_contacted_at',
        'next_follow_up_on',
        'email_delivery_status',
        'email_sent_at',
        'whatsapp_delivery_status',
        'whatsapp_sent_at',
        'whatsapp_delivery_error',
        'source',
        'referral_code',
        'ip_hash',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'software_helps_with' => 'array',
            'marketing_consent' => 'boolean',
            'score_overall' => 'integer',
            'score_visibility' => 'integer',
            'score_retention' => 'integer',
            'score_revenue_visibility' => 'integer',
            'score_reengagement' => 'integer',
            'estimated_opportunity_cents' => 'integer',
            'last_contacted_at' => 'datetime',
            'next_follow_up_on' => 'date',
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function assignedPlatformUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_platform_user_id');
    }
}
