<?php

namespace App\Domains\Integrations\DTO;

final class ProviderAdapterResultDto
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerReference = null,
        public readonly ?string $remoteStatus = null,
        public readonly ?string $failureMessage = null,
        public readonly ?string $failureCode = null,
        public readonly bool $simulated = false,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function delivered(
        string $providerReference,
        ?string $remoteStatus = null,
        bool $simulated = false,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            providerReference: $providerReference,
            remoteStatus: $remoteStatus ?? 'delivered',
            simulated: $simulated,
            metadata: $metadata,
        );
    }

    public static function failed(string $message, ?string $code = null, array $metadata = []): self
    {
        return new self(
            success: false,
            failureMessage: $message,
            failureCode: $code,
            metadata: $metadata,
        );
    }
}
