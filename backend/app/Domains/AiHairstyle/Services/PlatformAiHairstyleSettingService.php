<?php

namespace App\Domains\AiHairstyle\Services;

use App\Domains\AiHairstyle\Models\PlatformAiHairstyleSetting;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformAiHairstyleSettingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getOrCreate(): PlatformAiHairstyleSetting
    {
        $existing = PlatformAiHairstyleSetting::query()->orderBy('created_at')->first();
        if ($existing !== null) {
            return $existing;
        }

        $default = (string) config('ai_hairstyle.default_provider', PlatformAiHairstyleSetting::PROVIDER_STUB);
        if (! in_array($default, PlatformAiHairstyleSetting::providers(), true)) {
            $default = PlatformAiHairstyleSetting::PROVIDER_STUB;
        }

        if ($default === PlatformAiHairstyleSetting::PROVIDER_STUB
            && ! (bool) config('ai_hairstyle.allow_stub', true)
            && trim((string) config('ai_hairstyle.replicate.api_token')) !== '') {
            $default = PlatformAiHairstyleSetting::PROVIDER_REPLICATE;
        }

        return PlatformAiHairstyleSetting::query()->create([
            'provider' => $default,
        ]);
    }

    public function update(array $data): PlatformAiHairstyleSetting
    {
        $setting = $this->getOrCreate();

        return DB::transaction(function () use ($setting, $data) {
            if (! array_key_exists('provider', $data)) {
                return $setting;
            }

            $provider = (string) $data['provider'];
            if (! in_array($provider, PlatformAiHairstyleSetting::providers(), true)) {
                throw ValidationException::withMessages([
                    'provider' => ['Unknown AI hairstyle provider.'],
                ]);
            }

            if ($provider === PlatformAiHairstyleSetting::PROVIDER_STUB
                && ! (bool) config('ai_hairstyle.allow_stub', true)) {
                throw ValidationException::withMessages([
                    'provider' => ['Stub provider is disabled. Set AI_HAIRSTYLE_ALLOW_STUB=true only for local/CI.'],
                ]);
            }

            if ($provider === PlatformAiHairstyleSetting::PROVIDER_REPLICATE
                && trim((string) config('ai_hairstyle.replicate.api_token')) === '') {
                throw ValidationException::withMessages([
                    'provider' => ['Set REPLICATE_API_TOKEN before enabling the Replicate provider.'],
                ]);
            }

            $old = $setting->only(['provider']);
            $setting->forceFill(['provider' => $provider])->save();

            $this->auditLogger->log(
                'platform.ai_hairstyle_settings.updated',
                $setting,
                $old,
                $setting->only(['provider']),
            );

            return $setting->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(PlatformAiHairstyleSetting $setting): array
    {
        $allowStub = (bool) config('ai_hairstyle.allow_stub', true);
        $providers = [
            [
                'key' => PlatformAiHairstyleSetting::PROVIDER_STUB,
                'label' => 'Stub (local / testing)',
                'description' => 'Deterministic composites without calling Replicate.',
            ],
            [
                'key' => PlatformAiHairstyleSetting::PROVIDER_REPLICATE,
                'label' => 'Replicate',
                'description' => 'Production hairstyle generation via Replicate InstantID.',
            ],
        ];

        if (! $allowStub) {
            $providers = array_values(array_filter(
                $providers,
                fn (array $row) => $row['key'] !== PlatformAiHairstyleSetting::PROVIDER_STUB
            ));
        }

        return [
            'provider' => $setting->provider,
            'providers' => $providers,
            'allow_stub' => $allowStub,
            'replicate_configured' => trim((string) config('ai_hairstyle.replicate.api_token')) !== '',
            'replicate_model' => (string) config('ai_hairstyle.replicate.model'),
        ];
    }
}
