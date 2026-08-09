<?php

namespace App\Domains\Notifications\Services\WhatsApp;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\PlatformWhatsAppSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Tenant signup welcome WhatsApp — mirrors AuthMailService welcome trial / activation copy.
 * Uses platform Genius credentials (not tenant sessions).
 */
class PlatformSignupWhatsAppWelcomeService
{
    public const TYPE_TRIAL = 'welcome_trial';

    public const TYPE_ACTIVATION = 'activation';

    public const BANNER_STORAGE_PATH = 'platform/whatsapp/signup-welcome-banner.jpg';

    public const PUBLIC_API_PATH = '/api/v1/public/whatsapp/signup-welcome-banner';

    public const DEFAULT_TRIAL_BODY = <<<'TXT'
*Welcome to NeatMeet OS*

Hi {{name}},

Your 30-day free trial is ready. Use the temporary password below only to unlock *Creating Your Workspace*. At the end of setup you will choose your own permanent password — the temporary one stops working then.

*Email:* {{email}}
*Temporary unlock password:* {{password}}

Continue to Creating Your Workspace:
{{link}}

This temporary password is only for setup. If you did not request this, you can ignore this message.
TXT;

    public const DEFAULT_ACTIVATION_BODY = <<<'TXT'
*Activate your salon*

Hi {{name}},

Thanks for registering *{{salon}}*. Open the link below to confirm your email and set your password — your 30-day Basic trial starts when you activate.

Activate account:
{{link}}

If you did not sign up, you can ignore this message. This link expires in 48 hours.
TXT;

    public function __construct(
        private readonly GeniusWhatsAppClient $genius,
        private readonly WhatsAppCredentialResolver $credentials,
    ) {}

    /**
     * @param  array{name?: string, email?: string, password?: string, salon?: string, link?: string, phone?: string, tenant_id?: ?string}  $vars
     * @return array{sent: bool, skipped?: bool, reason?: string, error?: string}
     */
    public function send(string $type, array $vars): array
    {
        $settings = $this->getOrCreateSettings();
        if (! (bool) ($settings->signup_welcome_enabled ?? true)) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'signup_welcome_disabled'];
        }

        $phone = preg_replace('/\s+/', '', trim((string) ($vars['phone'] ?? ''))) ?? '';
        if ($phone === '' || ! str_starts_with($phone, '+')) {
            return ['sent' => false, 'skipped' => true, 'reason' => 'missing_phone'];
        }

        $resolved = $this->credentials->resolve(null);
        if (! $resolved['ready']) {
            Log::warning('Signup welcome WhatsApp skipped — platform WhatsApp not ready', [
                'type' => $type,
                'phone' => $phone,
            ]);

            return ['sent' => false, 'skipped' => true, 'reason' => 'platform_not_ready'];
        }

        $body = $this->renderBody($type, $settings, $vars);
        $bannerUrl = $this->resolveBannerPublicUrl($settings);

        if (filled($bannerUrl)) {
            try {
                $imageResult = $this->genius->send($phone, 'NeatMeet OS', $resolved['genius'], [
                    'type' => 'signup_welcome_banner',
                    'media_url' => $bannerUrl,
                    'signup_type' => $type,
                    'tenant_id' => $vars['tenant_id'] ?? null,
                ]);
                if (! ($imageResult['ok'] ?? false)) {
                    Log::warning('Signup welcome WhatsApp banner failed (text still sending)', [
                        'type' => $type,
                        'error' => $imageResult['error'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Signup welcome WhatsApp banner exception (text still sending)', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result = $this->genius->send($phone, $body, $resolved['genius'], [
            'type' => 'signup_welcome_'.$type,
            'tenant_id' => $vars['tenant_id'] ?? null,
        ]);

        if (! ($result['ok'] ?? false)) {
            Log::error('Signup welcome WhatsApp text failed', [
                'type' => $type,
                'phone' => $phone,
                'error' => $result['error'] ?? null,
            ]);

            return [
                'sent' => false,
                'error' => (string) ($result['error'] ?? 'Send failed'),
            ];
        }

        return ['sent' => true];
    }

    public function sendWelcomeTrial(User $user, string $plainPassword, ?string $phone = null): array
    {
        $phone ??= $this->phoneFromUserMeta($user);

        return $this->send(self::TYPE_TRIAL, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $plainPassword,
            'link' => rtrim((string) config('app.frontend_url'), '/').'/login?tab=signup&email='.urlencode($user->email),
            'phone' => $phone,
        ]);
    }

    public function sendActivation(User $user, Tenant $tenant, string $plainToken): array
    {
        return $this->send(self::TYPE_ACTIVATION, [
            'name' => $user->name,
            'email' => $user->email,
            'salon' => $tenant->trading_name ?: $tenant->name,
            'link' => rtrim((string) config('app.frontend_url'), '/').'/login?activate='.urlencode($plainToken),
            'phone' => $tenant->owner_whatsapp,
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeWelcome(PlatformWhatsAppSettings $settings): array
    {
        return [
            'enabled' => (bool) ($settings->signup_welcome_enabled ?? true),
            'trial_body' => filled($settings->signup_welcome_trial_body)
                ? (string) $settings->signup_welcome_trial_body
                : self::DEFAULT_TRIAL_BODY,
            'activation_body' => filled($settings->signup_welcome_activation_body)
                ? (string) $settings->signup_welcome_activation_body
                : self::DEFAULT_ACTIVATION_BODY,
            'placeholders' => [
                'trial' => ['{{name}}', '{{email}}', '{{password}}', '{{link}}'],
                'activation' => ['{{name}}', '{{email}}', '{{salon}}', '{{link}}'],
            ],
            'banner' => [
                'path' => $settings->signup_welcome_banner_path,
                'url' => $this->resolveBannerPublicUrl($settings),
                'mime' => $settings->signup_welcome_banner_mime,
                'has_data' => filled($settings->signup_welcome_banner_data),
            ],
            'defaults' => [
                'trial_body' => self::DEFAULT_TRIAL_BODY,
                'activation_body' => self::DEFAULT_ACTIVATION_BODY,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateWelcome(array $data): array
    {
        $settings = $this->getOrCreateSettings();
        $payload = [];

        if (array_key_exists('signup_welcome_enabled', $data)) {
            $payload['signup_welcome_enabled'] = (bool) $data['signup_welcome_enabled'];
        }
        if (array_key_exists('signup_welcome_trial_body', $data)) {
            $body = trim((string) ($data['signup_welcome_trial_body'] ?? ''));
            $payload['signup_welcome_trial_body'] = $body === '' ? null : $body;
        }
        if (array_key_exists('signup_welcome_activation_body', $data)) {
            $body = trim((string) ($data['signup_welcome_activation_body'] ?? ''));
            $payload['signup_welcome_activation_body'] = $body === '' ? null : $body;
        }

        if ($payload !== []) {
            $settings->update($payload);
        }

        return $this->serializeWelcome($settings->fresh() ?? $settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function storeBanner(\Illuminate\Http\UploadedFile $file): array
    {
        $settings = $this->getOrCreateSettings();
        $binary = file_get_contents($file->getRealPath());
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'image' => ['Could not read uploaded banner image.'],
            ]);
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
        $path = 'platform/whatsapp/signup-welcome-banner.'.$ext;

        Storage::disk('public')->put($path, $binary);
        $publicUrl = $this->laravelPublicBannerUrl();

        $settings->update([
            'signup_welcome_banner_path' => $path,
            'signup_welcome_banner_url' => $publicUrl,
            'signup_welcome_banner_mime' => $mime,
            'signup_welcome_banner_data' => base64_encode($binary),
        ]);

        return $this->serializeWelcome($settings->fresh() ?? $settings);
    }

    public function clearBanner(): array
    {
        $settings = $this->getOrCreateSettings();
        if (filled($settings->signup_welcome_banner_path)) {
            Storage::disk('public')->delete((string) $settings->signup_welcome_banner_path);
        }
        $settings->update([
            'signup_welcome_banner_path' => null,
            'signup_welcome_banner_url' => null,
            'signup_welcome_banner_mime' => null,
            'signup_welcome_banner_data' => null,
        ]);

        return $this->serializeWelcome($settings->fresh() ?? $settings);
    }

    public function resolveBannerBinary(?PlatformWhatsAppSettings $settings = null): ?string
    {
        $settings ??= $this->getOrCreateSettings();
        if (filled($settings->signup_welcome_banner_data)) {
            $decoded = base64_decode((string) $settings->signup_welcome_banner_data, true);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        if (filled($settings->signup_welcome_banner_path)
            && Storage::disk('public')->exists((string) $settings->signup_welcome_banner_path)
        ) {
            $binary = Storage::disk('public')->get((string) $settings->signup_welcome_banner_path);

            return is_string($binary) && $binary !== '' ? $binary : null;
        }

        return null;
    }

    public function resolveBannerMime(?PlatformWhatsAppSettings $settings = null): string
    {
        $settings ??= $this->getOrCreateSettings();

        return filled($settings->signup_welcome_banner_mime)
            ? (string) $settings->signup_welcome_banner_mime
            : 'image/jpeg';
    }

    private function getOrCreateSettings(): PlatformWhatsAppSettings
    {
        $settings = PlatformWhatsAppSettings::query()->orderBy('created_at')->first();
        if ($settings) {
            return $settings;
        }

        return PlatformWhatsAppSettings::query()->create([
            'enabled' => false,
            'provider' => PlatformWhatsAppSettings::PROVIDER_GENIUS,
            'base_url' => config('whatsapp.genius.base_url'),
            'api_key' => config('whatsapp.genius.api_key'),
            'session_id' => config('whatsapp.genius.session_id'),
            'signup_welcome_enabled' => true,
        ]);
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, salon?: string, link?: string}  $vars
     */
    private function renderBody(string $type, PlatformWhatsAppSettings $settings, array $vars): string
    {
        $template = match ($type) {
            self::TYPE_TRIAL => filled($settings->signup_welcome_trial_body)
                ? (string) $settings->signup_welcome_trial_body
                : self::DEFAULT_TRIAL_BODY,
            self::TYPE_ACTIVATION => filled($settings->signup_welcome_activation_body)
                ? (string) $settings->signup_welcome_activation_body
                : self::DEFAULT_ACTIVATION_BODY,
            default => self::DEFAULT_ACTIVATION_BODY,
        };

        return strtr($template, [
            '{{name}}' => (string) ($vars['name'] ?? 'there'),
            '{{email}}' => (string) ($vars['email'] ?? ''),
            '{{password}}' => (string) ($vars['password'] ?? ''),
            '{{salon}}' => (string) ($vars['salon'] ?? 'your salon'),
            '{{link}}' => (string) ($vars['link'] ?? ''),
        ]);
    }

    private function resolveBannerPublicUrl(?PlatformWhatsAppSettings $settings): ?string
    {
        if ($settings === null || (! filled($settings->signup_welcome_banner_data)
            && ! filled($settings->signup_welcome_banner_path))) {
            return null;
        }

        $canonical = $this->laravelPublicBannerUrl();
        if ($canonical !== '') {
            return $canonical;
        }

        $url = trim((string) ($settings->signup_welcome_banner_url ?? ''));

        return $url !== '' ? $url : null;
    }

    private function laravelPublicBannerUrl(): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl.self::PUBLIC_API_PATH;
    }

    private function phoneFromUserMeta(User $user): ?string
    {
        $meta = is_array($user->signup_meta) ? $user->signup_meta : [];
        $phone = preg_replace('/\s+/', '', trim((string) ($meta['whatsapp'] ?? $meta['phone'] ?? ''))) ?? '';

        return $phone !== '' ? $phone : null;
    }
}
