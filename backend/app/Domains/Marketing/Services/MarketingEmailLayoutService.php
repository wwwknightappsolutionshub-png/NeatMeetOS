<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Identity\Models\Tenant;

/**
 * Shared branded email chrome applied at render/preview/send time.
 * Template body_html stores inner content only; this service owns header/footer.
 */
class MarketingEmailLayoutService
{
    public const CHROME_MARKER = 'data-nm-email-chrome="1"';

    public const POWERED_BY = 'Powered by NeatMeet OS';

    /**
     * Wrap inner HTML in tenant-branded header + signature footer.
     */
    public function wrap(Tenant $tenant, string $innerHtml): string
    {
        if ($innerHtml === '' || $this->alreadyWrapped($innerHtml)) {
            return $innerHtml;
        }

        $branding = $tenant->getBranding();
        $primary = $this->safeColor((string) ($branding['primary_color'] ?? ''), '#18181b');
        $brandName = trim((string) ($branding['brand_display_name'] ?? ''))
            ?: (string) ($tenant->trading_name ?: $tenant->name ?: 'Your salon');
        $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
        $supportEmail = trim((string) ($branding['support_email'] ?? ''));
        $supportPhone = trim((string) ($branding['support_phone'] ?? ''));

        $fontLink = 'https://fonts.googleapis.com/css2?family=Anek+Latin:wght@400;600;700&display=swap';
        $fontStack = "'Anek Latin', Arial, Helvetica, sans-serif";
        $chromeAttr = self::CHROME_MARKER;
        $brandEsc = $this->e($brandName);
        $poweredEsc = $this->e(self::POWERED_BY);

        $logoBlock = $logoUrl !== ''
            ? '<img src="'.$this->e($logoUrl).'" alt="'.$brandEsc.'" width="120" style="display:block;max-width:120px;height:auto;margin:0 0 10px;border:0;" />'
            : '';

        $supportLines = '';
        if ($supportEmail !== '') {
            $supportEsc = $this->e($supportEmail);
            $supportLines .= '<p style="margin:0 0 4px;color:#71717a;font-size:12px;">'
                .'<a href="mailto:'.$supportEsc.'" style="color:#71717a;text-decoration:underline;">'.$supportEsc.'</a>'
                .'</p>';
        }
        if ($supportPhone !== '') {
            $supportLines .= '<p style="margin:0 0 4px;color:#71717a;font-size:12px;">'.$this->e($supportPhone).'</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="{$fontLink}" rel="stylesheet">
<style type="text/css">
  .nm-cta { background: {$primary} !important; color: #ffffff !important; }
</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
  <div {$chromeAttr} style="font-family:{$fontStack};font-size:16px;line-height:1.55;color:#18181b;max-width:560px;margin:0 auto;padding:24px 12px;">
    <div style="background:{$primary};color:#ffffff;padding:20px 24px;border-radius:12px 12px 0 0;">
      {$logoBlock}
      <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">{$brandEsc}</p>
    </div>
    <div style="background:#ffffff;border:1px solid #e4e4e7;border-top:none;padding:24px;border-radius:0 0 12px 12px;">
      {$innerHtml}
      <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e4e4e7;">
        <p style="margin:0 0 6px;font-weight:600;color:#18181b;">{$brandEsc}</p>
        {$supportLines}
        <p style="margin:12px 0 0;font-size:11px;color:#a1a1aa;letter-spacing:0.04em;">{$poweredEsc}</p>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    public function alreadyWrapped(string $html): bool
    {
        return str_contains($html, self::CHROME_MARKER)
            || str_contains($html, self::POWERED_BY);
    }

    private function safeColor(string $color, string $fallback): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) === 1) {
            return $color;
        }

        return $fallback;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
