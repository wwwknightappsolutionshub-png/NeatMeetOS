<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Platform auth emails (activation, magic login, password reset).
 * Uses Laravel Mail so delivery does not depend on tenant notification preferences.
 */
class AuthMailService
{
    /**
     * @param  Collection<int, User>  $admins
     */
    public function sendPlatformTenantSignup(Collection $admins, Tenant $tenant, User $owner, string $desiredPlan): void
    {
        if ($admins->isEmpty()) {
            return;
        }

        $salon = e($tenant->trading_name ?: $tenant->name);
        $slug = e($tenant->slug);
        $ownerEmail = e($owner->email);
        $whatsapp = e((string) ($tenant->owner_whatsapp ?? '—'));
        $plan = e($desiredPlan);
        $url = $this->frontendUrl('/platform/tenants');

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#b45309;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS · Super admin</p>
    <h1 style="margin:8px 0 0;font-size:22px;">New salon signup</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;line-height:1.5;"><strong>{$salon}</strong> (<code>{$slug}</code>) just registered and is pending activation.</p>
    <ul style="margin:0 0 16px;padding-left:18px;line-height:1.6;color:#44403c;">
      <li>Owner: {$ownerEmail}</li>
      <li>WhatsApp: {$whatsapp}</li>
      <li>Desired plan: {$plan}</li>
      <li>Status: pending_activation</li>
    </ul>
    <p style="margin:24px 0;"><a href="{$url}" style="display:inline-block;background:#b45309;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Open tenants</a></p>
  </div>
</div>
HTML;

        foreach ($admins as $admin) {
            Mail::html($html, function ($message) use ($admin, $salon) {
                $message->to($admin->email, $admin->name)
                    ->subject('New NeatMeet signup: '.$salon);
            });
        }
    }

    /**
     * Welcome email for marketing lead capture (temporary password + login link).
     * $plainPassword must never be logged by callers.
     */
    public function sendWelcomeTrial(User $user, string $plainPassword): void
    {
        $loginUrl = $this->frontendUrl('/login?tab=signup&email='.urlencode($user->email));
        $name = e($user->name);
        $email = e($user->email);
        $password = e($plainPassword);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS</p>
    <h1 style="margin:8px 0 0;font-size:22px;">Welcome to NeatMeet OS</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;">Hi {$name},</p>
    <p style="margin:0 0 12px;line-height:1.5;">Your 30-day free trial is ready. Use the temporary password below only to unlock <strong>Creating Your Workspace</strong>. At the end of setup you will choose your own permanent password — the temporary one stops working then.</p>
    <p style="margin:0 0 8px;line-height:1.5;"><strong>Email:</strong> {$email}</p>
    <p style="margin:0 0 16px;line-height:1.5;"><strong>Temporary unlock password:</strong> <code style="background:#f5f5f4;padding:2px 6px;border-radius:4px;">{$password}</code></p>
    <p style="margin:24px 0;"><a href="{$loginUrl}" style="display:inline-block;background:#2f5a45;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Continue to Creating Your Workspace</a></p>
    <p style="margin:0;font-size:12px;color:#78716c;line-height:1.5;">This temporary password is only for setup. If you did not request this, you can ignore this email.</p>
  </div>
</div>
HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)
                ->subject('Welcome to NeatMeet OS — your trial login');
        });
    }

    public function sendTenantActivation(User $user, string $plainToken, string $tenantName): void
    {
        $url = $this->frontendUrl('/login?activate='.urlencode($plainToken));
        $name = e($user->name);
        $salon = e($tenantName);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS</p>
    <h1 style="margin:8px 0 0;font-size:22px;">Activate your salon</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;">Hi {$name},</p>
    <p style="margin:0 0 12px;line-height:1.5;">Thanks for registering <strong>{$salon}</strong>. Click below to confirm your email and set your password — your 30-day Basic trial starts when you activate.</p>
    <p style="margin:24px 0;"><a href="{$url}" style="display:inline-block;background:#2f5a45;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Activate account</a></p>
    <p style="margin:0;font-size:12px;color:#78716c;line-height:1.5;">If you did not sign up, you can ignore this email. This link expires in 48 hours.</p>
  </div>
</div>
HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)
                ->subject('Activate your NeatMeet OS salon');
        });
    }

    public function sendMagicLogin(User $user, string $plainToken): void
    {
        $url = $this->frontendUrl('/login?magic='.urlencode($plainToken));
        $name = e($user->name);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS</p>
    <h1 style="margin:8px 0 0;font-size:22px;">Your magic sign-in link</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;">Hi {$name},</p>
    <p style="margin:0 0 12px;line-height:1.5;">Use the button below to sign in without a password. This link works once and expires in 15 minutes.</p>
    <p style="margin:24px 0;"><a href="{$url}" style="display:inline-block;background:#2f5a45;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Sign in</a></p>
    <p style="margin:0;font-size:12px;color:#78716c;">If you did not request this, you can ignore this email.</p>
  </div>
</div>
HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)
                ->subject('Your NeatMeet OS magic link');
        });
    }

    public function sendPasswordReset(User $user, string $plainToken): void
    {
        $url = $this->frontendResetUrl($plainToken);
        $name = e($user->name);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#18181b;">
  <div style="background:#2f5a45;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0;">
    <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">NeatMeet OS</p>
    <h1 style="margin:8px 0 0;font-size:22px;">Reset your password</h1>
  </div>
  <div style="border:1px solid #e7e5e4;border-top:0;padding:24px;border-radius:0 0 12px 12px;">
    <p style="margin:0 0 12px;">Hi {$name},</p>
    <p style="margin:0 0 12px;line-height:1.5;">We received a request to reset your password. Click below to choose a new one. This link expires in 60 minutes.</p>
    <p style="margin:24px 0;"><a href="{$url}" style="display:inline-block;background:#2f5a45;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">Reset password</a></p>
    <p style="margin:0;font-size:12px;color:#78716c;">If you did not request a reset, you can ignore this email.</p>
  </div>
</div>
HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)
                ->subject('Reset your NeatMeet OS password');
        });
    }

    public function frontendResetUrl(string $plainToken): string
    {
        return $this->frontendUrl('/login?reset='.urlencode($plainToken));
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
