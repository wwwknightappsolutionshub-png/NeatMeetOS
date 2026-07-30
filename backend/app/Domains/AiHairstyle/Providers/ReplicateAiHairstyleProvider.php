<?php

namespace App\Domains\AiHairstyle\Providers;

use App\Domains\AiHairstyle\Contracts\AiHairstyleProvider;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStyleCatalogue;
use App\Shared\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Replicate-backed hairstyle composites.
 * Selfie is sent as a data URI for inference only; original is not stored on the public disk.
 * Styles are created in parallel and polled under a shared wall-clock deadline.
 */
class ReplicateAiHairstyleProvider implements AiHairstyleProvider
{
    public function name(): string
    {
        return 'replicate';
    }

    public function generate(AiHairstyleSession $session, string $selfieAbsolutePath): array
    {
        $token = trim((string) config('ai_hairstyle.replicate.api_token'));
        if ($token === '') {
            throw ValidationException::withMessages([
                'provider' => ['Replicate API token is not configured (REPLICATE_API_TOKEN).'],
            ]);
        }

        if (! is_file($selfieAbsolutePath)) {
            throw ValidationException::withMessages([
                'selfie' => ['Selfie file is missing for generation.'],
            ]);
        }

        $imageDataUri = $this->toDataUri($selfieAbsolutePath);
        $model = trim((string) config('ai_hairstyle.replicate.model', 'zsxkib/instant-id'));
        $baseUrl = rtrim((string) config('ai_hairstyle.replicate.base_url'), '/');
        $pollMs = max(500, (int) config('ai_hairstyle.replicate.poll_interval_ms', 1500));
        $timeout = max(30, (int) config('ai_hairstyle.replicate.poll_timeout_seconds', 120));

        $pending = [];
        foreach (AiHairstyleStyleCatalogue::styles() as $index => $style) {
            $prediction = $this->createPrediction($baseUrl, $token, $model, [
                'image' => $imageDataUri,
                'prompt' => $style['prompt'],
                'negative_prompt' => 'blurry, low quality, deformed, extra limbs, watermark, text',
            ]);

            $predictionId = (string) ($prediction['id'] ?? '');
            if ($predictionId === '') {
                throw ValidationException::withMessages([
                    'provider' => ['Replicate prediction id missing.'],
                ]);
            }

            $pending[$style['key']] = [
                'index' => $index,
                'style' => $style,
                'prediction_id' => $predictionId,
            ];
        }

        $completed = $this->waitForAllPredictions($baseUrl, $token, $pending, $pollMs, $timeout);

        $downloadUrls = [];
        foreach ($pending as $key => $info) {
            $outputUrl = $this->firstOutputUrl($completed[$key]['output'] ?? null);
            if ($outputUrl === null) {
                throw ValidationException::withMessages([
                    'provider' => ["Replicate returned no image for style {$key}."],
                ]);
            }
            $downloadUrls[$key] = $outputUrl;
        }

        $downloadResponses = Http::pool(fn ($pool) => collect($downloadUrls)
            ->map(fn (string $url, string $key) => $pool->as($key)->timeout(60)->get($url))
            ->all());

        $rows = [];
        foreach ($pending as $key => $info) {
            $outputUrl = $downloadUrls[$key];
            $binary = $downloadResponses[$key] ?? null;
            if ($binary === null || ! $binary->successful()) {
                throw ValidationException::withMessages([
                    'provider' => ["Failed to download Replicate output for {$key}."],
                ]);
            }

            $ext = $this->guessExtension($outputUrl);
            $path = sprintf(
                'ai_hairstyle/%s/%s/%s.%s',
                $session->tenant_id,
                $session->id,
                $key,
                $ext,
            );

            Storage::disk('public')->put($path, $binary->body());

            $rows[] = [
                'composite_image_url' => PublicStorageUrl::fromDiskPath($path),
                'style_label' => $info['style']['label'],
                'style_key' => $info['style']['key'],
                'sort_order' => $info['index'],
                'provider_meta' => [
                    'provider' => $this->name(),
                    'replicate_prediction_id' => $info['prediction_id'],
                    'model' => $model,
                ],
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);

        return $rows;
    }

    /**
     * @param  array<string, array{index: int, style: array<string, mixed>, prediction_id: string}>  $pending
     * @return array<string, array<string, mixed>>
     */
    private function waitForAllPredictions(
        string $baseUrl,
        string $token,
        array $pending,
        int $pollMs,
        int $timeoutSeconds,
    ): array {
        $deadline = microtime(true) + $timeoutSeconds;
        $completed = [];

        while (count($completed) < count($pending)) {
            if (microtime(true) >= $deadline) {
                throw ValidationException::withMessages([
                    'provider' => ['Replicate prediction timed out.'],
                ]);
            }

            foreach ($pending as $key => $info) {
                if (isset($completed[$key])) {
                    continue;
                }

                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get("{$baseUrl}/predictions/{$info['prediction_id']}");

                if (! $response->successful()) {
                    throw ValidationException::withMessages([
                        'provider' => ['Failed to poll Replicate prediction status.'],
                    ]);
                }

                $payload = $response->json();
                $status = (string) ($payload['status'] ?? '');

                if (in_array($status, ['succeeded', 'failed', 'canceled'], true)) {
                    if ($status !== 'succeeded') {
                        $error = is_string($payload['error'] ?? null) ? $payload['error'] : $status;
                        throw ValidationException::withMessages([
                            'provider' => ["Replicate prediction {$status}: {$error}"],
                        ]);
                    }
                    $completed[$key] = $payload;
                }
            }

            if (count($completed) < count($pending)) {
                usleep($pollMs * 1000);
            }
        }

        return $completed;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createPrediction(string $baseUrl, string $token, string $model, array $input): array
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->post("{$baseUrl}/models/{$model}/predictions", [
                'input' => $input,
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'provider' => [
                    'Replicate prediction failed to start: '.$response->json('detail', $response->body()),
                ],
            ]);
        }

        return $response->json();
    }

    private function toDataUri(string $absolutePath): string
    {
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([
                'selfie' => ['Could not read selfie for AI generation.'],
            ]);
        }

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function firstOutputUrl(mixed $output): ?string
    {
        if (is_string($output) && str_starts_with($output, 'http')) {
            return $output;
        }

        if (is_array($output)) {
            foreach ($output as $item) {
                if (is_string($item) && str_starts_with($item, 'http')) {
                    return $item;
                }
                if (is_array($item)) {
                    $nested = $this->firstOutputUrl($item);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }
        }

        return null;
    }

    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }
}
