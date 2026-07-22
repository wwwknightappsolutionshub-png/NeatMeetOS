<?php

namespace App\Shared\Commerce\DTO;

readonly class CommerceEventDto
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventName,
        public string $tenantId,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public ?string $emittedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'tenant_id' => $this->tenantId,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'payload' => $this->payload,
            'emitted_at' => $this->emittedAt,
        ];
    }
}
