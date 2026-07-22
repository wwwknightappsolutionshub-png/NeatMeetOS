<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\PlatformUpgradeTemplate;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\PlatformUpgradeCatalogue;
use Illuminate\Support\Facades\Mail;

class PlatformUpgradeMailService
{
    public function sendDay7(
        User $owner,
        Tenant $tenant,
        PlatformUpgradeTemplate $template,
        string $path,
        string $upgradeUrl,
    ): void {
        $html = $this->wrapEmail(
            $this->render($template->headline ?? '', $owner, $tenant, 5, null),
            $this->render($template->body_html ?? '', $owner, $tenant, 5, null),
            $this->featureBlocks($template),
            $this->render($template->cta_label ?? 'Upgrade', $owner, $tenant, 5, null),
            $upgradeUrl,
            $tenant,
            false,
            null,
            null,
        );

        $subject = $this->render($template->subject ?? 'Upgrade your NeatMeet plan', $owner, $tenant, 5, null);

        Mail::html($html, function ($message) use ($owner, $subject) {
            $message->to($owner->email, $owner->name)->subject($subject);
        });
    }

    public function sendDay21(
        User $owner,
        Tenant $tenant,
        PlatformUpgradeTemplate $template,
        string $path,
        string $claimUrl,
        int $discountPercent,
        ?\DateTimeInterface $trialEndsAt,
        int $daysLeft,
    ): void {
        $trialLabel = $trialEndsAt?->format('j M Y, H:i') ?? 'soon';
        $countdownLabel = $daysLeft <= 0
            ? 'Trial ending now'
            : $daysLeft.' day'.($daysLeft === 1 ? '' : 's').' left';

        $body = $this->render($template->body_html ?? '', $owner, $tenant, $discountPercent, $trialLabel);
        $headline = $this->render($template->headline ?? 'Upgrade Within this time', $owner, $tenant, $discountPercent, $trialLabel);
        $cta = $this->render($template->cta_label ?? 'Claim 5% Discount', $owner, $tenant, $discountPercent, $trialLabel);

        $html = $this->wrapEmail(
            $headline,
            $body,
            $this->featureBlocks($template),
            $cta,
            $claimUrl,
            $tenant,
            true,
            $countdownLabel,
            $trialLabel,
        );

        $subject = $this->render(
            $template->subject ?? 'Upgrade within this time',
            $owner,
            $tenant,
            $discountPercent,
            $trialLabel,
        );

        Mail::html($html, function ($message) use ($owner, $subject) {
            $message->to($owner->email, $owner->name)->subject($subject);
        });
    }

    private function featureBlocks(PlatformUpgradeTemplate $template): string
    {
        $cases = is_array($template->use_cases) ? $template->use_cases : [];
        if ($cases === []) {
            return '';
        }
        $items = '';
        foreach ($cases as $uc) {
            $label = e((string) ($uc['label'] ?? ''));
            $text = e((string) ($uc['text'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items .= '<li style="margin:0 0 10px;"><strong>'.$label.'</strong> — '.$text.'</li>';
        }

        return $items === '' ? '' : '<ul style="margin:0 0 18px;padding-left:18px;line-height:1.55;color:#44403c;">'.$items.'</ul>';
    }

    private function wrapEmail(
        string $headline,
        string $bodyHtml,
        string $featureListHtml,
        string $ctaLabel,
        string $ctaUrl,
        Tenant $tenant,
        bool $showCountdown,
        ?string $countdownLabel,
        ?string $trialLabel,
    ): string {
        $salon = e($tenant->trading_name ?: $tenant->name);
        $primary = e((string) ($tenant->getBranding()['primary_color'] ?? '#2f5a45'));
        $headlineSafe = $headline; // already escaped via render or contains intended HTML from admin
        $countdownBlock = '';
        if ($showCountdown) {
            $countdownBlock = <<<HTML
  <div style="margin:0 0 20px;padding:16px;border:1px solid #e7e5e4;border-radius:12px;background:#fafaf9;text-align:center;">
    <p style="margin:0 0 6px;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#78716c;">Upgrade Within this time</p>
    <p style="margin:0;font-size:28px;font-weight:700;color:#1c1917;">{$countdownLabel}</p>
    <p style="margin:8px 0 0;font-size:13px;color:#78716c;">Trial ends {$trialLabel}</p>
  </div>
HTML;
        }

        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:{$primary};color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS · {$salon}</p>
    <h1 style="margin:8px 0 0;font-size:22px;">{$headlineSafe}</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    {$countdownBlock}
    <div style="margin:0 0 16px;line-height:1.55;">{$bodyHtml}</div>
    {$featureListHtml}
    <p style="margin:24px 0 0;"><a href="{$ctaUrl}" style="display:inline-block;background:{$primary};color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">{$ctaLabel}</a></p>
  </div>
</div>
HTML;
    }

    private function render(
        string $text,
        User $owner,
        Tenant $tenant,
        int $discountPercent,
        ?string $trialEndsLabel,
    ): string {
        $first = trim(explode(' ', $owner->name)[0] ?? $owner->name);

        return strtr($text, [
            '{{salon_name}}' => e($tenant->trading_name ?: $tenant->name),
            '{{owner_first_name}}' => e($first),
            '{{owner_name}}' => e($owner->name),
            '{{discount_percent}}' => (string) $discountPercent,
            '{{trial_ends_at}}' => e($trialEndsLabel ?? ''),
            '{{target_plan}}' => '',
        ]);
    }
}
