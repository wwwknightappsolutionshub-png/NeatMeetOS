<?php

namespace App\Domains\Notifications\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Laravel Mail transport for operational notification emails.
 * Used when Mailgun is not configured so booking confirmations still leave the box.
 */
class NotificationMailTransport
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public function send(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): array
    {
        $to = trim($to);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email recipient.'];
        }

        $safeSubject = e($subject !== '' ? $subject : 'Message from your salon');
        $paragraphs = collect(preg_split("/\r\n|\n|\r/", $bodyText) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => '<p style="margin:0 0 12px;line-height:1.5;">'.e($line).'</p>')
            ->implode('');

        $html = $bodyHtml && trim(strip_tags($bodyHtml)) !== ''
            ? $bodyHtml
            : <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#14532d;color:#fff;padding:18px 22px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet</p>
    <h1 style="margin:8px 0 0;font-size:20px;">{$safeSubject}</h1>
  </div>
  <div style="border:1px solid #e4e4e7;border-top:0;padding:22px;border-radius:0 0 12px 12px;">
    {$paragraphs}
  </div>
</div>
HTML;

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject !== '' ? $subject : 'Message from your salon');
            });

            return ['ok' => true];
        } catch (Throwable $e) {
            Log::warning('NotificationMailTransport failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
