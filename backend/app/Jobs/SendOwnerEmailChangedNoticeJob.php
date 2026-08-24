<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One-shot notice after a platform-assisted owner email change.
 */
class SendOwnerEmailChangedNoticeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $loginEmail,
        public readonly string $applicationName = 'NeatMeet Saloon',
    ) {}

    public function handle(): void
    {
        $to = strtolower(trim($this->recipientEmail));
        $login = strtolower(trim($this->loginEmail));
        $appName = trim($this->applicationName) !== '' ? trim($this->applicationName) : 'NeatMeet Saloon';

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('owner_email_changed_notice.invalid_recipient', ['email' => $this->recipientEmail]);

            return;
        }

        $subject = 'Congratulations, your email has now changed';
        $appEsc = e($appName);
        $loginEsc = e($login);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <h1 style="margin:0;font-size:22px;line-height:1.3;">Congratulations, your email has now changed</h1>
  </div>
  <div style="border:1px solid #e4e4e7;border-top:none;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 14px;line-height:1.55;color:#444;">
      Your email change request on &ldquo;{$appEsc}&rdquo; Application has been successful.
      You can now login with your new email id <strong>{$loginEsc}</strong>
      and the password you created during account setup.
    </p>
    <p style="margin:0;font-size:13px;color:#71717a;">
      If you did not request this change, contact NeatMeet support immediately.
    </p>
  </div>
</div>
HTML;

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::warning('owner_email_changed_notice.failed', [
                'email' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
