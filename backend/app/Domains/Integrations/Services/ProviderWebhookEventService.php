<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\ProviderWebhookProcessingStatus;
use App\Domains\Integrations\Models\ProviderAccount;
use App\Domains\Integrations\Models\ProviderWebhookEvent;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProviderWebhookEventService
{
    public function __construct(
        private readonly IntegrationsScopeValidator $scope,
        private readonly AuditLogger $auditLogger,
        private readonly ProviderWebhookNormalizer $normalizer,
        private readonly ProviderWebhookSignatureVerifier $signatures,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ProviderWebhookEvent>
     */
    public function list(array $filters = []): Collection
    {
        $query = ProviderWebhookEvent::query()
            ->with('providerAccount')
            ->orderByDesc('received_at');

        if (! empty($filters['driver'])) {
            $query->where('driver', $filters['driver']);
        }

        if (! empty($filters['processing_status'])) {
            $query->where('processing_status', $filters['processing_status']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $query->where('received_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('received_at', '<=', $filters['to']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): ProviderWebhookEvent
    {
        return $this->scope->findWebhookEvent($id)->load('providerAccount');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     */
    public function ingest(
        string $driver,
        array $payload,
        ?array $headers = null,
        ?string $tenantId = null,
        ?string $providerAccountId = null,
        ?string $eventType = null,
        ?string $externalEventId = null,
        ?string $rawBody = null,
    ): ProviderWebhookEvent {
        $account = null;
        $requestedTenantId = $tenantId;
        $headers = $headers ?? [];

        if ($providerAccountId !== null) {
            $account = ProviderAccount::withoutGlobalScopes()->find($providerAccountId);
            if ($account === null) {
                abort(404, 'Provider account not found.');
            }
            if ($requestedTenantId !== null && $requestedTenantId !== $account->tenant_id) {
                abort(422, 'tenant_id does not match provider account.');
            }
            $tenantId = $account->tenant_id;
        } elseif ($tenantId === null) {
            $tenantId = $this->resolveTenantFromPayload($payload, $driver);
        }

        if ($account === null && $tenantId !== null) {
            $account = ProviderAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('driver', $driver)
                ->whereNull('archived_at')
                ->orderByDesc('is_default')
                ->first();
        }

        $rawBody ??= json_encode($payload) ?: '';
        $verification = $this->signatures->verify($driver, $rawBody, $payload, $headers, $account);

        if ($verification['checked'] && $verification['valid'] === false
            && (bool) config('integrations.webhooks.require_valid_signature', true)) {
            abort(401, 'Invalid webhook signature.');
        }

        if (! $verification['checked']
            && (bool) config('integrations.webhooks.require_secret', false)) {
            abort(401, 'Webhook secret is required for this provider account.');
        }

        return DB::transaction(function () use (
            $driver,
            $payload,
            $headers,
            $tenantId,
            $account,
            $eventType,
            $externalEventId,
            $verification,
        ) {
            $normalized = $this->normalizer->normalize($driver, $payload, $headers);
            $metadata = $normalized['metadata'];
            $metadata['signature_check'] = $verification['checked']
                ? ($verification['valid'] ? 'valid' : 'invalid')
                : 'skipped';
            $metadata['signature_reason'] = $verification['reason'];

            $event = ProviderWebhookEvent::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'provider_account_id' => $account?->id,
                'category' => $account?->category,
                'driver' => $driver,
                'event_type' => $eventType ?? $normalized['event_type'],
                'external_event_id' => $externalEventId ?? $normalized['external_event_id'],
                'received_at' => now(),
                'processing_status' => ProviderWebhookProcessingStatus::RECEIVED,
                'signature_valid' => $verification['valid'],
                'payload_json' => $payload,
                'headers_json' => $headers,
                'metadata_json' => $metadata,
            ]);

            $this->auditLogger->log('provider_webhook.received', $event, null, [
                'driver' => $driver,
                'event_type' => $event->event_type,
                'signature_valid' => $verification['valid'],
            ]);

            return $event;
        });
    }

    public function markProcessed(ProviderWebhookEvent $event, ?array $resolution = null): ProviderWebhookEvent
    {
        return $this->updateProcessingStatus($event, ProviderWebhookProcessingStatus::PROCESSED, null, $resolution);
    }

    public function markFailed(ProviderWebhookEvent $event, string $error): ProviderWebhookEvent
    {
        return $this->updateProcessingStatus($event, ProviderWebhookProcessingStatus::FAILED, $error);
    }

    public function markIgnored(ProviderWebhookEvent $event, ?string $reason = null): ProviderWebhookEvent
    {
        return $this->updateProcessingStatus($event, ProviderWebhookProcessingStatus::IGNORED, $reason);
    }

    /**
     * @param  array<string, mixed>|null  $resolution
     */
    private function updateProcessingStatus(
        ProviderWebhookEvent $event,
        string $status,
        ?string $error = null,
        ?array $resolution = null,
    ): ProviderWebhookEvent {
        $event->processing_status = $status;
        $event->processed_at = now();
        $event->processing_error = $error;

        if ($resolution !== null) {
            $event->resolved_source_domain = $resolution['source_domain'] ?? null;
            $event->resolved_source_type = $resolution['source_type'] ?? null;
            $event->resolved_source_id = $resolution['source_id'] ?? null;
        }

        $event->save();

        $auditAction = match ($status) {
            ProviderWebhookProcessingStatus::PROCESSED => 'provider_webhook.processed',
            ProviderWebhookProcessingStatus::FAILED => 'provider_webhook.failed',
            default => 'provider_webhook.processed',
        };

        $this->auditLogger->log($auditAction, $event, null, [
            'processing_status' => $status,
        ]);

        return $event->fresh('providerAccount');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTenantFromPayload(array $payload, string $driver): ?string
    {
        $tenantId = $payload['tenant_id'] ?? $payload['metadata']['tenant_id'] ?? null;

        if ($tenantId !== null) {
            return (string) $tenantId;
        }

        $accountId = $payload['provider_account_id'] ?? null;

        if ($accountId !== null) {
            return ProviderAccount::withoutGlobalScopes()->find($accountId)?->tenant_id;
        }

        return null;
    }
}
