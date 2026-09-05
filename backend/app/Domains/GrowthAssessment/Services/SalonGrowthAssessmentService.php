<?php

namespace App\Domains\GrowthAssessment\Services;

use App\Domains\GrowthAssessment\Models\SalonGrowthAssessment;
use App\Domains\Notifications\Services\PlatformWhatsAppSettingsService;
use App\Shared\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalonGrowthAssessmentService
{
    public function __construct(
        private readonly SalonGrowthScoringService $scoring,
        private readonly SalonGrowthAssessmentMailService $mail,
        private readonly PlatformWhatsAppSettingsService $whatsApp,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{assessment: SalonGrowthAssessment, result: array<string, mixed>}
     */
    public function submit(array $data, ?string $ip, ?string $userAgent): array
    {
        $honeypot = trim((string) ($data['hp_trap'] ?? $data['website'] ?? ''));
        if ($honeypot !== '') {
            throw ValidationException::withMessages([
                'form' => ['Unable to submit assessment.'],
            ]);
        }

        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $answers['business_name'] = (string) ($data['business_name'] ?? $answers['business_name'] ?? '');
        $answers['business_type'] = (string) ($data['business_type'] ?? $answers['business_type'] ?? '');
        $answers['staff_band'] = (string) ($data['staff_band'] ?? $answers['staff_band'] ?? '');
        $answers['customers_per_month_band'] = (string) ($data['customers_per_month_band'] ?? $answers['customers_per_month_band'] ?? '');

        $scored = $this->scoring->score($answers);
        $phone = trim((string) ($data['phone'] ?? ''));

        $assessment = SalonGrowthAssessment::query()->create([
            'public_token' => Str::random(48),
            'business_name' => trim((string) $data['business_name']),
            'business_type' => (string) $data['business_type'],
            'staff_band' => $answers['staff_band'] !== '' ? $answers['staff_band'] : null,
            'customers_per_month_band' => $answers['customers_per_month_band'] !== '' ? $answers['customers_per_month_band'] : null,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => $phone !== '' ? $phone : null,
            'phone_normalized' => $this->normalizePhone($phone),
            'postcode' => ($p = trim((string) ($data['postcode'] ?? ''))) !== '' ? $p : null,
            'marketing_consent' => (bool) ($data['marketing_consent'] ?? false),
            'answers' => $answers,
            'score_overall' => $scored['score_overall'],
            'score_visibility' => $scored['score_visibility'],
            'score_retention' => $scored['score_retention'],
            'score_revenue_visibility' => $scored['score_revenue_visibility'],
            'score_reengagement' => $scored['score_reengagement'],
            'estimated_opportunity_cents' => $scored['estimated_opportunity_cents'],
            'primary_opportunity' => $scored['primary_opportunity'],
            'primary_opportunity_label' => $scored['primary_opportunity_label'],
            'sales_conversation_hint' => $scored['sales_conversation_hint'],
            'uses_software' => (string) ($answers['uses_software'] ?? ''),
            'software_helps_with' => is_array($answers['software_helps_with'] ?? null)
                ? $answers['software_helps_with']
                : null,
            'software_satisfaction' => isset($answers['software_satisfaction'])
                ? (string) $answers['software_satisfaction']
                : null,
            'tracking_methods' => (string) ($answers['tracking_method'] ?? ''),
            'lead_status' => 'new',
            'email_delivery_status' => 'pending',
            'whatsapp_delivery_status' => ! empty($data['send_whatsapp']) ? 'pending' : 'not_requested',
            'source' => (string) ($data['source'] ?? 'landing'),
            'referral_code' => ($ref = trim((string) ($data['referral_code'] ?? ''))) !== '' ? $ref : null,
            'ip_hash' => $ip ? hash('sha256', $ip) : null,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
        ]);

        $this->deliverEmail($assessment);
        if (! empty($data['send_whatsapp'])) {
            $this->deliverWhatsApp($assessment);
        }

        $this->auditLogger->log('platform.growth_assessment.submitted', $assessment, null, [
            'business_type' => $assessment->business_type,
            'score_overall' => $assessment->score_overall,
            'estimated_opportunity_cents' => $assessment->estimated_opportunity_cents,
        ]);

        return [
            'assessment' => $assessment->fresh(),
            'result' => $this->publicResultPayload($assessment->fresh(), $scored),
        ];
    }

    public function findByPublicToken(string $token): ?SalonGrowthAssessment
    {
        return SalonGrowthAssessment::query()->where('public_token', $token)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicResultPayload(SalonGrowthAssessment $assessment, ?array $scored = null): array
    {
        $capabilities = $scored['neatmeet_capabilities'] ?? [
            'Track customers across visits',
            'Identify returning vs first-time customers',
            'Monitor customer retention',
            'Identify customers who may be due to return',
            'Build loyalty and rewards',
            'Re-engage customers',
            'Understand repeat-revenue opportunity',
            'Connect customer activity to bookings, loyalty and marketing',
        ];

        return [
            'public_token' => $assessment->public_token,
            'business_name' => $assessment->business_name,
            'assessed_at' => $assessment->created_at?->toIso8601String(),
            'score_overall' => $assessment->score_overall,
            'score_visibility' => $assessment->score_visibility,
            'score_retention' => $assessment->score_retention,
            'score_revenue_visibility' => $assessment->score_revenue_visibility,
            'score_reengagement' => $assessment->score_reengagement,
            'estimated_opportunity_cents' => $assessment->estimated_opportunity_cents,
            'estimated_opportunity_display' => '£'.number_format($assessment->estimated_opportunity_cents / 100, 0),
            'primary_opportunity' => $assessment->primary_opportunity,
            'primary_opportunity_label' => $assessment->primary_opportunity_label,
            'opportunity_narrative' => $scored['opportunity_narrative'] ?? $this->narrativeFor($assessment),
            'neatmeet_capabilities' => $capabilities,
            'estimate_disclaimer' => $scored['estimate_disclaimer'] ?? 'This estimate is based on your answers and is intended to highlight potential opportunity, not represent your actual accounting revenue.',
            'email_delivery_status' => $assessment->email_delivery_status,
            'whatsapp_delivery_status' => $assessment->whatsapp_delivery_status,
            'indicative_note' => 'This is an indicative assessment based on the information you provided.',
        ];
    }

    public function requestWhatsApp(SalonGrowthAssessment $assessment): SalonGrowthAssessment
    {
        if ($assessment->phone_normalized === null) {
            throw ValidationException::withMessages([
                'phone' => ['A mobile / WhatsApp number is required.'],
            ]);
        }

        $assessment->whatsapp_delivery_status = 'pending';
        $assessment->save();
        $this->deliverWhatsApp($assessment);

        return $assessment->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForPlatform(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $q = SalonGrowthAssessment::query()->with('assignedPlatformUser:id,name,email');

        if (! empty($filters['lead_status'])) {
            $q->where('lead_status', $filters['lead_status']);
        }
        if (! empty($filters['business_type'])) {
            $q->where('business_type', $filters['business_type']);
        }
        if (! empty($filters['uses_software'])) {
            $q->where('uses_software', $filters['uses_software']);
        }
        if (! empty($filters['postcode'])) {
            $q->where('postcode', 'ilike', '%'.trim((string) $filters['postcode']).'%');
        }
        if (isset($filters['score_min']) && $filters['score_min'] !== '') {
            $q->where('score_overall', '>=', (int) $filters['score_min']);
        }
        if (isset($filters['score_max']) && $filters['score_max'] !== '') {
            $q->where('score_overall', '<=', (int) $filters['score_max']);
        }
        if (isset($filters['opportunity_min_cents']) && $filters['opportunity_min_cents'] !== '') {
            $q->where('estimated_opportunity_cents', '>=', (int) $filters['opportunity_min_cents']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('business_name', 'ilike', $term)
                    ->orWhere('contact_name', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term)
                    ->orWhere('phone', 'ilike', $term);
            });
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = [
            'created_at',
            'score_overall',
            'estimated_opportunity_cents',
            'business_name',
            'lead_status',
        ];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        return $q->orderBy($sort, $dir)->paginate(min(100, max(1, $perPage)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLead(SalonGrowthAssessment $assessment, array $data): SalonGrowthAssessment
    {
        $before = $assessment->only([
            'lead_status',
            'assigned_platform_user_id',
            'internal_notes',
            'last_contacted_at',
            'next_follow_up_on',
        ]);

        if (array_key_exists('lead_status', $data)) {
            $status = (string) $data['lead_status'];
            if (! in_array($status, SalonGrowthAssessment::LEAD_STATUSES, true)) {
                throw ValidationException::withMessages(['lead_status' => ['Invalid lead status.']]);
            }
            $assessment->lead_status = $status;
        }
        if (array_key_exists('assigned_platform_user_id', $data)) {
            $assessment->assigned_platform_user_id = $data['assigned_platform_user_id'] ?: null;
        }
        if (array_key_exists('internal_notes', $data)) {
            $assessment->internal_notes = $data['internal_notes'];
        }
        if (array_key_exists('last_contacted_at', $data)) {
            $assessment->last_contacted_at = $data['last_contacted_at'] ?: null;
        }
        if (array_key_exists('next_follow_up_on', $data)) {
            $assessment->next_follow_up_on = $data['next_follow_up_on'] ?: null;
        }

        $assessment->save();

        $this->auditLogger->log('platform.growth_assessment.lead_updated', $assessment, null, [
            'before' => $before,
            'after' => $assessment->only(array_keys($before)),
        ]);

        return $assessment->fresh(['assignedPlatformUser:id,name,email']);
    }

    /**
     * @return array<string, mixed>
     */
    public function platformDetail(SalonGrowthAssessment $assessment): array
    {
        $assessment->loadMissing('assignedPlatformUser:id,name,email');
        $tier = $this->opportunityTier($assessment);

        return [
            'id' => $assessment->id,
            'public_token' => $assessment->public_token,
            'business_name' => $assessment->business_name,
            'business_type' => $assessment->business_type,
            'staff_band' => $assessment->staff_band,
            'customers_per_month_band' => $assessment->customers_per_month_band,
            'contact_name' => $assessment->contact_name,
            'email' => $assessment->email,
            'phone' => $assessment->phone,
            'phone_normalized' => $assessment->phone_normalized,
            'postcode' => $assessment->postcode,
            'marketing_consent' => $assessment->marketing_consent,
            'answers' => $assessment->answers,
            'score_overall' => $assessment->score_overall,
            'score_visibility' => $assessment->score_visibility,
            'score_retention' => $assessment->score_retention,
            'score_revenue_visibility' => $assessment->score_revenue_visibility,
            'score_reengagement' => $assessment->score_reengagement,
            'estimated_opportunity_cents' => $assessment->estimated_opportunity_cents,
            'estimated_opportunity_display' => '£'.number_format($assessment->estimated_opportunity_cents / 100, 0),
            'primary_opportunity' => $assessment->primary_opportunity,
            'primary_opportunity_label' => $assessment->primary_opportunity_label,
            'sales_conversation_hint' => $assessment->sales_conversation_hint,
            'uses_software' => $assessment->uses_software,
            'software_helps_with' => $assessment->software_helps_with,
            'software_satisfaction' => $assessment->software_satisfaction,
            'tracking_methods' => $assessment->tracking_methods,
            'lead_status' => $assessment->lead_status,
            'assigned_platform_user' => $assessment->assignedPlatformUser,
            'internal_notes' => $assessment->internal_notes,
            'last_contacted_at' => $assessment->last_contacted_at?->toIso8601String(),
            'next_follow_up_on' => $assessment->next_follow_up_on?->format('Y-m-d'),
            'email_delivery_status' => $assessment->email_delivery_status,
            'email_sent_at' => $assessment->email_sent_at?->toIso8601String(),
            'whatsapp_delivery_status' => $assessment->whatsapp_delivery_status,
            'whatsapp_sent_at' => $assessment->whatsapp_sent_at?->toIso8601String(),
            'whatsapp_delivery_error' => $assessment->whatsapp_delivery_error,
            'source' => $assessment->source,
            'referral_code' => $assessment->referral_code,
            'created_at' => $assessment->created_at?->toIso8601String(),
            'prospect_opportunity' => [
                'tier' => $tier,
                'label' => match ($tier) {
                    'high' => 'High-value opportunity',
                    'medium' => 'Solid opportunity',
                    default => 'Nurture opportunity',
                },
                'growth_score' => $assessment->score_overall,
                'estimated_opportunity_display' => '£'.number_format($assessment->estimated_opportunity_cents / 100, 0).'/month',
                'current_system' => $this->currentSystemLabel($assessment),
                'main_weakness' => $assessment->primary_opportunity_label,
                'suggested_sales_conversation' => $assessment->sales_conversation_hint,
            ],
        ];
    }

    private function deliverEmail(SalonGrowthAssessment $assessment): void
    {
        try {
            if ($this->mail->sendResults($assessment)) {
                $assessment->forceFill([
                    'email_delivery_status' => 'sent',
                    'email_sent_at' => now(),
                ])->save();
            } else {
                $assessment->forceFill(['email_delivery_status' => 'skipped'])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('growth_assessment.email_failed', [
                'id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);
            $assessment->forceFill(['email_delivery_status' => 'failed'])->save();
        }
    }

    private function deliverWhatsApp(SalonGrowthAssessment $assessment): void
    {
        $to = $assessment->phone_normalized;
        if ($to === null) {
            $assessment->forceFill([
                'whatsapp_delivery_status' => 'failed',
                'whatsapp_delivery_error' => 'No normalised phone number.',
            ])->save();

            return;
        }

        $opp = '£'.number_format($assessment->estimated_opportunity_cents / 100, 0);
        $message = "NeatMeet OS — Salon Growth Assessment for {$assessment->business_name}\n\n"
            ."Growth score: {$assessment->score_overall}/100\n"
            ."Visibility: {$assessment->score_visibility} · Retention: {$assessment->score_retention}\n"
            ."Revenue visibility: {$assessment->score_revenue_visibility} · Re-engagement: {$assessment->score_reengagement}\n"
            ."Estimated repeat-revenue opportunity: {$opp}/month (indicative)\n"
            ."Biggest opportunity: {$assessment->primary_opportunity_label}\n\n"
            .'This is an indicative assessment based on your answers — not accounting revenue. '
            .'See how NeatMeet can help: '.rtrim((string) config('app.frontend_url'), '/').'/#product';

        try {
            $result = $this->whatsApp->sendOperational($to, $message, [
                'tenant_id' => null,
                'purpose' => 'growth_assessment_results',
                'assessment_id' => $assessment->id,
            ]);

            if (! empty($result['ok'])) {
                $assessment->forceFill([
                    'whatsapp_delivery_status' => 'sent',
                    'whatsapp_sent_at' => now(),
                    'whatsapp_delivery_error' => null,
                ])->save();
            } else {
                $assessment->forceFill([
                    'whatsapp_delivery_status' => 'failed',
                    'whatsapp_delivery_error' => (string) ($result['error'] ?? 'WhatsApp delivery failed'),
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('growth_assessment.whatsapp_failed', [
                'id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);
            $assessment->forceFill([
                'whatsapp_delivery_status' => 'failed',
                'whatsapp_delivery_error' => $e->getMessage(),
            ])->save();
        }
    }

    private function narrativeFor(SalonGrowthAssessment $assessment): string
    {
        return match ((string) $assessment->primary_opportunity) {
            'retention' => 'Your answers suggest room to strengthen how systematically you track and encourage return visits.',
            'revenue_visibility' => 'You may not yet have a clear view of how much potential repeat revenue sits in customers who do not return when expected.',
            'reengagement' => 'There is an opportunity to build more consistent follow-up when customers go quiet after a visit.',
            default => 'Based on your answers, you may be serving many customers without having enough information to bring them back automatically.',
        };
    }

    private function opportunityTier(SalonGrowthAssessment $assessment): string
    {
        $cents = (int) $assessment->estimated_opportunity_cents;
        $score = (int) $assessment->score_overall;
        if ($cents >= 300000 || ($score <= 55 && $cents >= 150000)) {
            return 'high';
        }
        if ($cents >= 100000 || $score <= 70) {
            return 'medium';
        }

        return 'nurture';
    }

    private function currentSystemLabel(SalonGrowthAssessment $assessment): string
    {
        if ($assessment->uses_software === 'yes') {
            $helps = is_array($assessment->software_helps_with) ? implode(', ', $assessment->software_helps_with) : '';
            $track = (string) $assessment->tracking_methods;

            return trim($track.($helps !== '' ? " ({$helps})" : '')) ?: 'Software in use';
        }

        return $assessment->uses_software === 'no' ? 'No dedicated software' : 'Unknown';
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\s+/', '', trim((string) $phone)) ?? '';
        if ($phone === '') {
            return null;
        }
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        if (str_starts_with($phone, '00')) {
            return '+'.substr($phone, 2);
        }
        if (str_starts_with($phone, '0')) {
            return '+44'.substr($phone, 1);
        }

        return '+'.$phone;
    }
}
