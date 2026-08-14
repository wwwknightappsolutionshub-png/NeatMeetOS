<?php

namespace App\Jobs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\WhatsApp\PlatformSignupWhatsAppWelcomeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Async signup welcome WhatsApp so public signup HTTP responses are not blocked by Genius.
 */
class SendTenantSignupWelcomeWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public int $timeout = 45;

    /**
     * @param  array<string, mixed>  $vars
     */
    public function __construct(
        public string $type,
        public array $vars,
    ) {}

    public static function dispatchTrial(User $user, string $plainPassword, ?string $phone = null): void
    {
        $phone ??= (function (User $user): ?string {
            $meta = is_array($user->signup_meta) ? $user->signup_meta : [];
            $value = preg_replace('/\s+/', '', trim((string) ($meta['whatsapp'] ?? $meta['phone'] ?? ''))) ?? '';

            return $value !== '' ? $value : null;
        })($user);

        if ($phone === null || $phone === '') {
            return;
        }

        self::release(new self(PlatformSignupWhatsAppWelcomeService::TYPE_TRIAL, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $plainPassword,
            'link' => rtrim((string) config('app.frontend_url'), '/').'/login?tab=signup&email='.urlencode($user->email),
            'phone' => $phone,
        ]));
    }

    public static function dispatchActivation(User $user, Tenant $tenant, string $plainToken): void
    {
        $phone = preg_replace('/\s+/', '', trim((string) ($tenant->owner_whatsapp ?? ''))) ?? '';
        if ($phone === '') {
            return;
        }

        self::release(new self(PlatformSignupWhatsAppWelcomeService::TYPE_ACTIVATION, [
            'name' => $user->name,
            'email' => $user->email,
            'salon' => $tenant->trading_name ?: $tenant->name,
            'link' => rtrim((string) config('app.frontend_url'), '/').'/login?activate='.urlencode($plainToken),
            'phone' => $phone,
            'tenant_id' => $tenant->id,
        ]));
    }

    private static function release(self $job): void
    {
        if (app()->runningUnitTests()) {
            dispatch($job);

            return;
        }

        // Run after the HTTP response so welcome WhatsApp is not blocked if the queue worker is idle.
        dispatch(function () use ($job) {
            $job->handle(app(PlatformSignupWhatsAppWelcomeService::class));
        })->afterResponse();
    }

    public function handle(PlatformSignupWhatsAppWelcomeService $welcome): void
    {
        Log::info('Signup welcome WhatsApp job started', [
            'type' => $this->type,
            'attempt' => $this->attempts(),
        ]);

        $result = $welcome->send($this->type, $this->vars);
        if (($result['skipped'] ?? false) === true) {
            return;
        }
        if (! ($result['sent'] ?? false)) {
            throw new \RuntimeException($result['error'] ?? 'Signup welcome WhatsApp failed');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Signup welcome WhatsApp job failed permanently', [
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
