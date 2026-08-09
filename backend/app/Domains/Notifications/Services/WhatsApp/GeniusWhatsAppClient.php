<?php

namespace App\Domains\Notifications\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genius REST WhatsApp sender — POST {base_url}/api/send with x-api-key.
 */
class GeniusWhatsAppClient
{
    /**
     * @param  array{api_key?: ?string, session_id?: ?string, base_url?: ?string}  $credentials
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, error?: string, status?: int}
     */
    public function send(
        string $toPhone,
        string $message,
        array $credentials,
        array $context = [],
    ): array {
        $apiKey = $credentials['api_key'] ?? null;
        $sessionId = $credentials['session_id'] ?? null;
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? config('whatsapp.genius.base_url')), '/');

        if (! filled($apiKey) || ! filled($sessionId)) {
            Log::error('WhatsApp (Genius): api_key/session_id missing', [
                'to' => $toPhone,
                'context' => $context,
            ]);

            return [
                'ok' => false,
                'error' => 'Genius API key or session ID is missing.',
            ];
        }

        $number = preg_replace('/\D+/', '', $toPhone) ?: $toPhone;
        $mediaUrl = isset($context['media_url']) ? trim((string) $context['media_url']) : '';
        $isImage = $mediaUrl !== '';

        $payload = [
            'sessionId' => $sessionId,
            'number' => $number,
            'type' => $isImage ? 'image' : 'text',
            'message' => $message,
            'source' => 'API',
        ];

        if ($isImage) {
            $payload['url'] = $mediaUrl;
            $payload['mediaUrl'] = $mediaUrl;
        }

        try {
            $response = Http::timeout(20)->connectTimeout(5)->withHeaders([
                'x-api-key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/api/send", $payload);
        } catch (\Throwable $e) {
            Log::error('WhatsApp (Genius): request failed', [
                'to' => $number,
                'error' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'ok' => false,
                'error' => 'Genius did not confirm delivery: '.$e->getMessage(),
            ];
        }

        if ($response->failed()) {
            Log::error('WhatsApp (Genius): API send failed', [
                'to' => $number,
                'status' => $response->status(),
                'body' => $response->body(),
                'context' => $context,
            ]);

            $body = trim($response->body());

            return [
                'ok' => false,
                'status' => $response->status(),
                'error' => $body !== ''
                    ? 'Genius API error (HTTP '.$response->status().'): '.$body
                    : 'Genius API error (HTTP '.$response->status().').',
            ];
        }

        Log::info('WhatsApp (Genius): message sent', [
            'to' => $number,
            'context_type' => $context['type'] ?? null,
        ]);

        return ['ok' => true];
    }
}
