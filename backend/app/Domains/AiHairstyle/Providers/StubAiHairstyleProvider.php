<?php

namespace App\Domains\AiHairstyle\Providers;

use App\Domains\AiHairstyle\Contracts\AiHairstyleProvider;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStyleCatalogue;
use App\Shared\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Deterministic stub provider for local/CI and fallback.
 * Validates the selfie path exists; never copies the original face to public storage.
 */
class StubAiHairstyleProvider implements AiHairstyleProvider
{
    public function name(): string
    {
        return 'stub';
    }

    public function generate(AiHairstyleSession $session, string $selfieAbsolutePath): array
    {
        if (! is_file($selfieAbsolutePath)) {
            throw ValidationException::withMessages([
                'selfie' => ['Selfie file is missing for generation.'],
            ]);
        }

        $rows = [];
        foreach (AiHairstyleStyleCatalogue::styles() as $index => $style) {
            $path = sprintf(
                'ai_hairstyle/%s/%s/%s.svg',
                $session->tenant_id,
                $session->id,
                $style['key'],
            );
            Storage::disk('public')->put($path, $this->compositeSvg($style['label'], $style['accent']));

            $rows[] = [
                'composite_image_url' => PublicStorageUrl::fromDiskPath($path),
                'style_label' => $style['label'],
                'style_key' => $style['key'],
                'sort_order' => $index,
                'provider_meta' => [
                    'provider' => $this->name(),
                    'stub' => true,
                    'nonce' => Str::lower(Str::random(8)),
                ],
            ];
        }

        return $rows;
    }

    private function compositeSvg(string $label, string $accent): string
    {
        $safe = htmlspecialchars($label, ENT_QUOTES | ENT_XML1);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="800" viewBox="0 0 640 800">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$accent}"/>
      <stop offset="100%" stop-color="#1c1917"/>
    </linearGradient>
  </defs>
  <rect width="640" height="800" fill="url(#bg)"/>
  <circle cx="320" cy="300" r="120" fill="rgba(255,255,255,0.12)"/>
  <text x="320" y="560" text-anchor="middle" fill="#fafaf9" font-family="Georgia, serif" font-size="36">{$safe}</text>
  <text x="320" y="610" text-anchor="middle" fill="rgba(250,250,249,0.7)" font-family="system-ui,sans-serif" font-size="18">AI look preview</text>
</svg>
SVG;
    }
}
