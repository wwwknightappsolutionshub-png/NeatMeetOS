<?php

namespace App\Domains\GrowthAssessment\Services;

use App\Domains\GrowthAssessment\Models\SalonGrowthAssessment;
use Illuminate\Support\Facades\Mail;

/**
 * Pre-tenant assessment result email (platform AuthMail-style HTML, not tenant marketing).
 */
class SalonGrowthAssessmentMailService
{
    public function sendResults(SalonGrowthAssessment $assessment): bool
    {
        $email = trim((string) $assessment->email);
        if ($email === '') {
            return false;
        }

        $name = e((string) ($assessment->contact_name ?: 'there'));
        $business = e($assessment->business_name);
        $date = e($assessment->created_at?->timezone(config('app.timezone'))->format('d M Y') ?? now()->format('d M Y'));
        $overall = (int) $assessment->score_overall;
        $vis = (int) $assessment->score_visibility;
        $ret = (int) $assessment->score_retention;
        $rev = (int) $assessment->score_revenue_visibility;
        $re = (int) $assessment->score_reengagement;
        $opp = e('£'.number_format($assessment->estimated_opportunity_cents / 100, 0));
        $primary = e((string) $assessment->primary_opportunity_label);
        $learnMore = e($this->frontendUrl('/assessment?token='.urlencode($assessment->public_token)));
        $book = e($this->frontendUrl('/login?tab=signup'));
        $ctaProduct = e($this->frontendUrl('/#product'));

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS</p>
    <h1 style="margin:8px 0 0;font-size:22px;">Your Salon Growth Assessment</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;">Hi {$name},</p>
    <p style="margin:0 0 12px;line-height:1.5;">Here is the indicative assessment for <strong>{$business}</strong> ({$date}). This is based on the information you provided — not a precise financial audit.</p>
    <p style="margin:0 0 8px;font-size:28px;font-weight:700;color:#2f5a45;">{$overall} / 100</p>
    <p style="margin:0 0 16px;color:#78716c;font-size:13px;">Your Salon Growth Score</p>
    <ul style="margin:0 0 16px;padding-left:18px;line-height:1.7;color:#44403c;">
      <li>Customer Visibility: {$vis}/100</li>
      <li>Retention: {$ret}/100</li>
      <li>Revenue Visibility: {$rev}/100</li>
      <li>Re-engagement: {$re}/100</li>
      <li>Estimated repeat-revenue opportunity: <strong>{$opp}/month</strong></li>
      <li>Biggest opportunity area: <strong>{$primary}</strong></li>
    </ul>
    <p style="margin:0 0 12px;line-height:1.5;color:#57534e;font-size:13px;">This estimate highlights potential opportunity — it does not represent your actual accounting revenue.</p>
    <p style="margin:0 0 8px;font-weight:600;">What NeatMeet can help you do</p>
    <ul style="margin:0 0 20px;padding-left:18px;line-height:1.6;color:#44403c;font-size:14px;">
      <li>Track customers across visits</li>
      <li>Identify returning vs first-time customers</li>
      <li>Monitor retention and customers due to return</li>
      <li>Build loyalty and re-engage quietly</li>
      <li>Connect activity to bookings, loyalty and marketing</li>
    </ul>
    <p style="margin:0 0 12px;line-height:1.5;">You've seen the opportunity. NeatMeet helps turn that visibility into action.</p>
    <p style="margin:20px 0 8px;">
      <a href="{$ctaProduct}" style="display:inline-block;background:#2f5a45;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">See how NeatMeet can help</a>
    </p>
    <p style="margin:0 0 8px;">
      <a href="{$book}" style="color:#2f5a45;font-weight:600;">Start a free trial</a>
      &nbsp;·&nbsp;
      <a href="{$learnMore}" style="color:#2f5a45;">View your results again</a>
    </p>
    <p style="margin:20px 0 0;font-size:12px;color:#78716c;line-height:1.5;">NeatMeet OS — get customers, know customers, serve them, bring them back, reward loyalty, grow repeat revenue.</p>
  </div>
</div>
HTML;

        Mail::html($html, function ($message) use ($assessment, $email, $business) {
            $message->to($email, (string) ($assessment->contact_name ?: $business))
                ->subject('Your NeatMeet Salon Growth Assessment — '.$business);
        });

        return true;
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
