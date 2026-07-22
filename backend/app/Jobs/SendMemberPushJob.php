<?php

namespace App\Jobs;

use App\Domains\Crm\Services\MemberPushDispatchService;
use App\Domains\Identity\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMemberPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  array{title?: string, body?: string, url?: string, data?: array<string, mixed>}  $payload
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $payload,
        public readonly ?string $clientId = null,
    ) {}

    public function handle(TenantContext $tenantContext, MemberPushDispatchService $push): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenantContext->set($tenant);

        if ($this->clientId !== null) {
            $push->sendToClient($this->clientId, $this->payload);

            return;
        }

        $push->sendToTenant($this->payload, $this->tenantId);
    }
}
