<?php

namespace App\Domains\GrowthAssessment\Services;

/**
 * Rule-based, explainable Free Salon Growth Assessment scoring (no ML).
 *
 * Formulas documented in MODULE.md and returned as metadata for transparency.
 */
class SalonGrowthScoringService
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array{
     *   score_visibility: int,
     *   score_retention: int,
     *   score_revenue_visibility: int,
     *   score_reengagement: int,
     *   score_overall: int,
     *   estimated_opportunity_cents: int,
     *   primary_opportunity: string,
     *   primary_opportunity_label: string,
     *   opportunity_narrative: string,
     *   sales_conversation_hint: string,
     *   neatmeet_capabilities: list<string>,
     *   estimate_disclaimer: string
     * }
     */
    public function score(array $answers): array
    {
        $visibility = $this->visibilityScore($answers);
        $retention = $this->retentionScore($answers);
        $revenueVis = $this->revenueVisibilityScore($answers);
        $reengage = $this->reengagementScore($answers);

        $overall = (int) round(
            ($visibility * 0.30) + ($retention * 0.30) + ($revenueVis * 0.20) + ($reengage * 0.20)
        );
        $overall = max(0, min(100, $overall));

        $opportunityCents = $this->estimateOpportunityCents($answers);
        $primary = $this->primaryOpportunity($visibility, $retention, $revenueVis, $reengage);

        return [
            'score_visibility' => $visibility,
            'score_retention' => $retention,
            'score_revenue_visibility' => $revenueVis,
            'score_reengagement' => $reengage,
            'score_overall' => $overall,
            'estimated_opportunity_cents' => $opportunityCents,
            'primary_opportunity' => $primary['key'],
            'primary_opportunity_label' => $primary['label'],
            'opportunity_narrative' => $primary['narrative'],
            'sales_conversation_hint' => $this->salesHint($answers, $primary['key'], $overall, $opportunityCents),
            'neatmeet_capabilities' => $this->capabilities(),
            'estimate_disclaimer' => 'This estimate is based on your answers and is intended to highlight potential opportunity, not represent your actual accounting revenue.',
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function visibilityScore(array $answers): int
    {
        $knowVisitors = match ((string) ($answers['knows_last_month_visitors'] ?? '')) {
            'yes_exactly' => 30,
            'approximately' => 18,
            'no' => 5,
            default => 10,
        };

        $knowReturn = match ((string) ($answers['knows_how_many_returned'] ?? '')) {
            'yes' => 25,
            'approximately' => 15,
            'no' => 5,
            default => 10,
        };

        $tracking = (string) ($answers['tracking_method'] ?? '');
        $trackingPts = match ($tracking) {
            'crm', 'loyalty_system' => 20,
            'booking_software' => 15,
            'spreadsheet' => 10,
            'notebook' => 5,
            'nothing' => 0,
            'other' => 8,
            default => 5,
        };

        $dueReturn = match ((string) ($answers['knows_when_due_return'] ?? '')) {
            'always' => 25,
            'sometimes' => 15,
            'rarely' => 8,
            'never' => 0,
            default => 8,
        };

        return max(0, min(100, $knowVisitors + $knowReturn + $trackingPts + $dueReturn));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function retentionScore(array $answers): int
    {
        $returnBand = match ((string) ($answers['return_percentage_band'] ?? '')) {
            'under_20' => 12,
            '20_40' => 32,
            '41_60' => 52,
            '61_80' => 74,
            'over_80' => 92,
            'not_sure' => 28,
            default => 28,
        };

        $methods = $this->encourageMethods($answers);
        $systematic = array_intersect($methods, [
            'loyalty_rewards',
            'sms',
            'whatsapp',
            'email',
            'next_appointment',
        ]);
        $methodPts = min(40, count($systematic) * 10);
        if (in_array('nothing', $methods, true) && count($methods) === 1) {
            $methodPts = 0;
        }

        $dueReturn = match ((string) ($answers['knows_when_due_return'] ?? '')) {
            'always' => 18,
            'sometimes' => 10,
            'rarely' => 4,
            'never' => 0,
            default => 5,
        };

        return max(0, min(100, $returnBand + $methodPts + $dueReturn));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function revenueVisibilityScore(array $answers): int
    {
        $spendKnown = ((string) ($answers['avg_spend_band'] ?? '')) !== 'not_sure' ? 40 : 15;

        $knowsMissed = match ((string) ($answers['knows_missed_revenue'] ?? '')) {
            'yes' => 40,
            'no' => 10,
            'not_sure' => 20,
            default => 15,
        };

        $satisfaction = match ((string) ($answers['software_satisfaction'] ?? '')) {
            'very_satisfied' => 20,
            'satisfied' => 16,
            'neutral' => 12,
            'not_very_satisfied' => 8,
            'not_at_all' => 4,
            default => ((string) ($answers['uses_software'] ?? '') === 'no') ? 10 : 12,
        };

        return max(0, min(100, $spendKnown + $knowsMissed + $satisfaction));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function reengagementScore(array $answers): int
    {
        $methods = $this->encourageMethods($answers);
        $score = 10;
        foreach (['loyalty_rewards', 'sms', 'whatsapp', 'email', 'next_appointment', 'discounts', 'phone_calls'] as $m) {
            if (in_array($m, $methods, true)) {
                $score += match ($m) {
                    'loyalty_rewards', 'next_appointment' => 14,
                    'sms', 'whatsapp', 'email' => 12,
                    'discounts', 'phone_calls' => 8,
                    default => 0,
                };
            }
        }
        if (in_array('nothing', $methods, true)) {
            $score = min($score, 15);
        }

        $dueReturn = match ((string) ($answers['knows_when_due_return'] ?? '')) {
            'always' => 20,
            'sometimes' => 12,
            'rarely' => 5,
            'never' => 0,
            default => 6,
        };

        return max(0, min(100, $score + $dueReturn));
    }

    /**
     * Estimated monthly repeat-revenue opportunity (indicative, not accounting).
     *
     * customers_mid × spend_mid × non_return_rate
     *
     * @param  array<string, mixed>  $answers
     */
    public function estimateOpportunityCents(array $answers): int
    {
        $customers = match ((string) ($answers['customers_per_month_band'] ?? '')) {
            '0_100' => 50,
            '101_250' => 175,
            '251_500' => 375,
            '501_1000' => 750,
            '1000_plus' => 1500,
            default => 175,
        };

        $spendPounds = match ((string) ($answers['avg_spend_band'] ?? '')) {
            'under_20' => 15,
            '20_40' => 30,
            '41_60' => 50,
            '61_80' => 70,
            '81_100' => 90,
            '100_plus' => 130,
            'not_sure' => 45,
            default => 45,
        };

        $nonReturn = match ((string) ($answers['return_percentage_band'] ?? '')) {
            'under_20' => 0.70,
            '20_40' => 0.50,
            '41_60' => 0.35,
            '61_80' => 0.20,
            'over_80' => 0.10,
            'not_sure' => 0.40,
            default => 0.40,
        };

        $pounds = (int) round($customers * $spendPounds * $nonReturn);

        return max(0, $pounds * 100);
    }

    /**
     * @return array{key: string, label: string, narrative: string}
     */
    private function primaryOpportunity(int $v, int $r, int $rev, int $re): array
    {
        $map = [
            'visibility' => [
                'score' => $v,
                'label' => 'Customer visibility',
                'narrative' => 'Based on your answers, you may be serving many customers without having enough information to bring them back automatically.',
            ],
            'retention' => [
                'score' => $r,
                'label' => 'Customer retention',
                'narrative' => 'Your answers suggest room to strengthen how systematically you track and encourage return visits.',
            ],
            'revenue_visibility' => [
                'score' => $rev,
                'label' => 'Revenue visibility',
                'narrative' => 'You may not yet have a clear view of how much potential repeat revenue sits in customers who do not return when expected.',
            ],
            'reengagement' => [
                'score' => $re,
                'label' => 'Customer re-engagement',
                'narrative' => 'There is an opportunity to build more consistent follow-up when customers go quiet after a visit.',
            ],
        ];

        $lowestKey = 'visibility';
        $lowest = 101;
        foreach ($map as $key => $row) {
            if ($row['score'] < $lowest) {
                $lowest = $row['score'];
                $lowestKey = $key;
            }
        }

        return [
            'key' => $lowestKey,
            'label' => $map[$lowestKey]['label'],
            'narrative' => $map[$lowestKey]['narrative'],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function salesHint(array $answers, string $primaryKey, int $overall, int $opportunityCents): string
    {
        $usesSoftware = ((string) ($answers['uses_software'] ?? '')) === 'yes';
        $tracking = (string) ($answers['tracking_method'] ?? '');
        $pounds = number_format($opportunityCents / 100, 0);

        if ($usesSoftware && in_array($tracking, ['booking_software', 'crm'], true)) {
            return "This business already uses {$tracking} but shows limited strength in {$primaryKey} (growth score {$overall}/100; indicative opportunity ~£{$pounds}/month). Frame NeatMeet as a growth and retention layer — not a replacement for booking alone.";
        }

        if (! $usesSoftware) {
            return "This business is not yet on dedicated management software (growth score {$overall}/100). Lead with visibility into return behaviour and the indicative ~£{$pounds}/month repeat-revenue opportunity.";
        }

        return "Primary weakness appears to be {$primaryKey}. Growth score {$overall}/100 with an indicative ~£{$pounds}/month repeat-revenue opportunity — discuss how NeatMeet connects customers, loyalty, and re-engagement.";
    }

    /**
     * @return list<string>
     */
    private function capabilities(): array
    {
        return [
            'Track customers across visits',
            'Identify returning vs first-time customers',
            'Monitor customer retention',
            'Identify customers who may be due to return',
            'Build loyalty and rewards',
            'Re-engage customers',
            'Understand repeat-revenue opportunity',
            'Connect customer activity to bookings, loyalty and marketing',
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    private function encourageMethods(array $answers): array
    {
        $raw = $answers['encourage_return_methods'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $raw)));
    }
}
