<?php

namespace App\Shared\Commerce\Services;

use App\Shared\Commerce\DTO\CommerceEventDto;
use App\Shared\Commerce\Models\CommerceEvent;

class CommerceEventPublisher
{
    public function publish(CommerceEventDto $event): CommerceEvent
    {
        return CommerceEvent::withoutGlobalScopes()->create([
            'tenant_id' => $event->tenantId,
            'event_name' => $event->eventName,
            'aggregate_type' => $event->aggregateType,
            'aggregate_id' => $event->aggregateId,
            'payload' => $event->payload,
            'emitted_at' => $event->emittedAt ?? now(),
        ]);
    }
}
