<?php

namespace App\Jobs;

use App\Domains\Identity\Services\TenantWorkspaceWelcomeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTenantWorkspaceWelcomeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $email,
        public readonly ?string $phone = null,
    ) {}

    public function handle(TenantWorkspaceWelcomeService $welcome): void
    {
        $welcome->sendToEmail($this->email, $this->phone);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('tenant.workspace_welcome.failed', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
