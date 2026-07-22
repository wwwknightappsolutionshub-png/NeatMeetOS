<?php

namespace App\Jobs;

use App\Domains\Notifications\Models\NotificationMessage;
use App\Domains\Notifications\Services\NotificationDispatchSimulationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNotificationMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $notificationMessageId,
    ) {}

    public function handle(
        TenantContext $tenantContext,
        NotificationDispatchSimulationService $dispatch,
    ): void {
        $tenant = \App\Domains\Identity\Models\Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenantContext->set($tenant);

        $message = NotificationMessage::query()->find($this->notificationMessageId);
        if ($message === null) {
            return;
        }

        $dispatch->dispatch($message);
    }
}
