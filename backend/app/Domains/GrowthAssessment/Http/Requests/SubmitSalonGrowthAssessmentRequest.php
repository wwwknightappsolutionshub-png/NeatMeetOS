<?php

namespace App\Domains\GrowthAssessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSalonGrowthAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:160'],
            'business_type' => ['required', 'string', Rule::in([
                'hair_salon',
                'barber_shop',
                'beauty_salon',
                'spa',
                'other',
            ])],
            'staff_band' => ['nullable', 'string', Rule::in([
                '1',
                '2_3',
                '4_8',
                '9_15',
                '16_plus',
            ])],
            'customers_per_month_band' => ['required', 'string', Rule::in([
                '0_100',
                '101_250',
                '251_500',
                '501_1000',
                '1000_plus',
            ])],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'marketing_consent' => ['required', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'source' => ['sometimes', 'string', 'max:64'],
            'referral_code' => ['nullable', 'string', 'max:64'],
            'hp_trap' => ['nullable', 'string', 'max:200'],
            'website' => ['nullable', 'string', 'max:200'],
            'answers' => ['required', 'array'],
            'answers.knows_last_month_visitors' => ['required', 'string', Rule::in(['yes_exactly', 'approximately', 'no'])],
            'answers.knows_how_many_returned' => ['required', 'string', Rule::in(['yes', 'approximately', 'no'])],
            'answers.tracking_method' => ['required', 'string', Rule::in([
                'booking_software',
                'spreadsheet',
                'notebook',
                'crm',
                'loyalty_system',
                'nothing',
                'other',
            ])],
            'answers.knows_when_due_return' => ['required', 'string', Rule::in(['always', 'sometimes', 'rarely', 'never'])],
            'answers.return_percentage_band' => ['required', 'string', Rule::in([
                'under_20',
                '20_40',
                '41_60',
                '61_80',
                'over_80',
                'not_sure',
            ])],
            'answers.encourage_return_methods' => ['required', 'array', 'min:1'],
            'answers.encourage_return_methods.*' => ['string', Rule::in([
                'loyalty_rewards',
                'sms',
                'whatsapp',
                'email',
                'phone_calls',
                'next_appointment',
                'discounts',
                'nothing',
            ])],
            'answers.avg_spend_band' => ['required', 'string', Rule::in([
                'under_20',
                '20_40',
                '41_60',
                '61_80',
                '81_100',
                '100_plus',
                'not_sure',
            ])],
            'answers.knows_missed_revenue' => ['required', 'string', Rule::in(['yes', 'no', 'not_sure'])],
            'answers.uses_software' => ['required', 'string', Rule::in(['yes', 'no'])],
            'answers.software_helps_with' => ['nullable', 'array'],
            'answers.software_helps_with.*' => ['string', Rule::in([
                'booking',
                'payments_pos',
                'customer_records',
                'loyalty',
                'marketing',
                'reporting',
                'all_of_the_above',
                'other',
            ])],
            'answers.software_satisfaction' => ['nullable', 'string', Rule::in([
                'very_satisfied',
                'satisfied',
                'neutral',
                'not_very_satisfied',
                'not_at_all',
            ])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $uses = (string) $this->input('answers.uses_software');
            if ($uses === 'yes') {
                $helps = $this->input('answers.software_helps_with');
                if (! is_array($helps) || count($helps) < 1) {
                    $validator->errors()->add('answers.software_helps_with', 'Please say what your software mainly helps with.');
                }
                if (! $this->filled('answers.software_satisfaction')) {
                    $validator->errors()->add('answers.software_satisfaction', 'Please rate how satisfied you are with bringing customers back.');
                }
            }
        });
    }
}
