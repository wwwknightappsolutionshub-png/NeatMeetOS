<?php

namespace App\Domains\AiHairstyle\Services;

use App\Domains\AiHairstyle\Contracts\AiHairstyleProvider;
use App\Domains\AiHairstyle\Models\PlatformAiHairstyleSetting;
use App\Domains\AiHairstyle\Providers\ReplicateAiHairstyleProvider;
use App\Domains\AiHairstyle\Providers\StubAiHairstyleProvider;
use Illuminate\Validation\ValidationException;

class AiHairstyleProviderResolver
{
    public function __construct(
        private readonly PlatformAiHairstyleSettingService $settings,
        private readonly StubAiHairstyleProvider $stub,
        private readonly ReplicateAiHairstyleProvider $replicate,
    ) {}

    public function resolve(): AiHairstyleProvider
    {
        $provider = $this->settings->getOrCreate()->provider;

        return match ($provider) {
            PlatformAiHairstyleSetting::PROVIDER_REPLICATE => $this->replicate,
            default => $this->stub,
        };
    }

    public function activeName(): string
    {
        return $this->resolve()->name();
    }

    /**
     * Fail closed when stub is disabled (production) or Replicate lacks a token.
     */
    public function assertGenerationAllowed(): void
    {
        $name = $this->activeName();

        if ($name === PlatformAiHairstyleSetting::PROVIDER_STUB
            && ! (bool) config('ai_hairstyle.allow_stub', true)) {
            throw ValidationException::withMessages([
                'provider' => [
                    'AI look generation is not configured for production. Enable Replicate in platform settings.',
                ],
            ]);
        }

        if ($name === PlatformAiHairstyleSetting::PROVIDER_REPLICATE
            && trim((string) config('ai_hairstyle.replicate.api_token')) === '') {
            throw ValidationException::withMessages([
                'provider' => ['REPLICATE_API_TOKEN is missing. Generation cannot start.'],
            ]);
        }
    }
}
