<?php

namespace App\Domains\Integrations\DTO;

use App\Domains\Integrations\Models\ProviderDeliveryAttempt;

final class ProviderDispatchResultDto
{
    public function __construct(
        public readonly string $providerDeliveryAttemptId,
        public readonly ?string $providerReference,
        public readonly string $status,
        public readonly ?string $sentAt = null,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $failedAt = null,
        public readonly ?string $failureMessage = null,
        public readonly bool $simulated = false,
        public readonly ?string $providerAccountId = null,
        public readonly ?string $driver = null,
    ) {}

    public static function fromAttempt(ProviderDeliveryAttempt $attempt, bool $simulated = false, ?string $driver = null): self
    {
        return new self(
            providerDeliveryAttemptId: $attempt->id,
            providerReference: $attempt->provider_reference,
            status: $attempt->status,
            sentAt: $attempt->sent_at?->toIso8601String(),
            deliveredAt: $attempt->delivered_at?->toIso8601String(),
            failedAt: $attempt->failed_at?->toIso8601String(),
            failureMessage: $attempt->failure_message,
            simulated: $simulated,
            providerAccountId: $attempt->provider_account_id,
            driver: $driver,
        );
    }
}
