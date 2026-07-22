<?php

namespace App\Domains\Integrations\DTO;

final class OutboundProviderDispatchDto
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $providerCategory,
        public readonly string $sourceDomain,
        public readonly string $sourceType,
        public readonly ?string $sourceId = null,
        public readonly ?string $relatedClientId = null,
        public readonly ?string $relatedAppointmentId = null,
        public readonly ?string $relatedPaymentTransactionId = null,
        public readonly ?string $purpose = null,
        public readonly ?string $recipientAddress = null,
        public readonly ?string $recipientPhone = null,
        public readonly ?string $subject = null,
        public readonly ?string $bodyText = null,
        public readonly ?array $payload = null,
        public readonly ?string $providerAccountId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?array $metadata = null,
        public readonly ?string $forcedStatus = null,
        public readonly ?string $forcedFailureMessage = null,
        public readonly ?string $providerReference = null,
    ) {}
}
