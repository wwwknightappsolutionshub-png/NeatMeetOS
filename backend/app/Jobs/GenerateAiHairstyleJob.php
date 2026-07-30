<?php

namespace App\Jobs;

use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Services\AiHairstyleProviderResolver;
use App\Domains\AiHairstyle\Services\AiHairstyleSessionService;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerateAiHairstyleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Parallel Replicate poll window (~120s) + create/download buffer. */
    public int $timeout = 360;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $sessionId,
        public readonly string $tempRelativePath,
    ) {}

    public function handle(
        TenantContext $tenantContext,
        AiHairstyleSessionService $sessions,
        AiHairstyleProviderResolver $resolver,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            $this->deleteTemp();

            return;
        }

        $tenantContext->set($tenant);

        $session = AiHairstyleSession::query()->with('previews')->find($this->sessionId);
        if ($session === null) {
            $this->deleteTemp();

            return;
        }

        $absolute = Storage::disk((string) config('ai_hairstyle.temp_disk', 'local'))
            ->path($this->tempRelativePath);

        try {
            if ($session->status !== AiHairstyleStatuses::SESSION_GENERATING) {
                return;
            }

            $provider = $resolver->resolve();
            $rows = $provider->generate($session, $absolute);
            $sessions->markReady($session, $rows);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Preview generation failed.';
            $this->failSession($sessions, $session, (string) $message);
        } catch (\Throwable $e) {
            Log::error('ai_hairstyle.generate_job_failed', [
                'tenant_id' => $this->tenantId,
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
            $this->failSession($sessions, $session, 'Preview generation failed. Please try again.');
        } finally {
            $this->deleteTemp();
        }
    }

    public function failed(?\Throwable $e): void
    {
        try {
            $tenant = Tenant::query()->find($this->tenantId);
            if ($tenant === null) {
                return;
            }
            app(TenantContext::class)->set($tenant);
            $session = AiHairstyleSession::query()->find($this->sessionId);
            if ($session && $session->status === AiHairstyleStatuses::SESSION_GENERATING) {
                app(AiHairstyleSessionService::class)->markFailed(
                    $session,
                    'Preview generation failed. Please try again.',
                );
            }
        } finally {
            $this->deleteTemp();
        }
    }

    private function failSession(
        AiHairstyleSessionService $sessions,
        AiHairstyleSession $session,
        string $message,
    ): void {
        $fresh = $session->fresh();
        if ($fresh && $fresh->status === AiHairstyleStatuses::SESSION_GENERATING) {
            $sessions->markFailed($fresh, $message);
        }
    }

    private function deleteTemp(): void
    {
        $disk = (string) config('ai_hairstyle.temp_disk', 'local');
        if (Storage::disk($disk)->exists($this->tempRelativePath)) {
            Storage::disk($disk)->delete($this->tempRelativePath);
        }
    }
}
