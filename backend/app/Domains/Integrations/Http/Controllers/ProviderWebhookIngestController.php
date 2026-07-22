<?php

namespace App\Domains\Integrations\Http\Controllers;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Integrations\Enums\ProviderDriver;
use App\Domains\Integrations\Http\Resources\ProviderWebhookEventResource;
use App\Domains\Integrations\Services\ProviderWebhookEventService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderWebhookIngestController extends Controller
{
    public function __construct(
        private readonly ProviderWebhookEventService $events,
    ) {}

    public function store(Request $request, string $driver): JsonResponse
    {
        if (! in_array($driver, ProviderDriver::all(), true)) {
            abort(404, 'Unknown provider driver.');
        }

        $validated = $request->validate([
            'tenant_id' => ['nullable', 'uuid'],
            'provider_account_id' => ['nullable', 'uuid'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'external_event_id' => ['nullable', 'string', 'max:255'],
        ]);

        $rawBody = $request->getContent();
        $payload = $request->all();
        if ($payload === []) {
            $decoded = json_decode($rawBody, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $rawBody];
        }

        $event = $this->events->ingest(
            driver: $driver,
            payload: $payload,
            headers: collect($request->headers->all())->map(fn ($v) => is_array($v) ? ($v[0] ?? null) : $v)->all(),
            tenantId: $validated['tenant_id'] ?? null,
            providerAccountId: $validated['provider_account_id'] ?? null,
            eventType: $validated['event_type'] ?? null,
            externalEventId: $validated['external_event_id'] ?? null,
            rawBody: $rawBody,
        );

        return ApiResponse::success(new ProviderWebhookEventResource($event), 'Webhook received', 201);
    }
}
