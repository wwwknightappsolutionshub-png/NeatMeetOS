<?php

namespace App\Jobs;

use App\Domains\Identity\Services\TenantSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * WhatsApp reminder ~10 minutes before a tenant admin session expires.
 * No-ops if the session was extended (expires_at no longer matches).
 */
class SendTenantSessionLogoutWarningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 45;

    public function __construct(
        public string $tokenId,
        public int $expectedExpiresAt,
    ) {}

    public function handle(TenantSessionService $sessions): void
    {
        $token = PersonalAccessToken::query()->find($this->tokenId);
        if ($token === null) {
            return;
        }

        $sessions->sendLogoutWarning($token, $this->expectedExpiresAt);
    }
}
