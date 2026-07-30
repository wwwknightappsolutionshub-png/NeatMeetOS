<?php

namespace App\Domains\AiHairstyle\Services;

use App\Domains\AiHairstyle\Models\AiHairstylePreview;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\Client;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiHairstyleSessionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function find(string $id): AiHairstyleSession
    {
        $session = AiHairstyleSession::query()->with('previews')->findOrFail($id);
        $this->assertTenant($session);

        return $session;
    }

    /**
     * @return Collection<int, AiHairstyleSession>
     */
    public function listByStatus(string $status, int $limit = 100): Collection
    {
        if (! in_array($status, AiHairstyleStatuses::sessionStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid session status.'],
            ]);
        }

        return AiHairstyleSession::query()
            ->with('previews')
            ->where('status', $status)
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{client_id?: string|null, expires_at?: \DateTimeInterface|null, metadata?: array<string, mixed>|null}  $data
     */
    public function createDraft(array $data = []): AiHairstyleSession
    {
        $tenantId = $this->requireTenantId();
        $clientId = $data['client_id'] ?? null;

        if ($clientId !== null) {
            $this->assertClientBelongsToTenant($clientId, $tenantId);
        }

        $session = AiHairstyleSession::query()->create([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'status' => AiHairstyleStatuses::SESSION_DRAFT,
            'expires_at' => $data['expires_at'] ?? now()->addDay(),
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->auditLogger->log('ai_hairstyle.session.created', $session, null, [
            'status' => $session->status,
            'client_id' => $session->client_id,
        ]);

        return $session->fresh(['previews']);
    }

    public function markGenerating(AiHairstyleSession $session, ?string $provider = null, ?string $externalJobId = null): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_GENERATING, [
            'error_message' => null,
            'provider' => $provider ?? $session->provider,
            'external_job_id' => $externalJobId ?? $session->external_job_id,
        ]);

        return $session->fresh(['previews']);
    }

    /**
     * @param  list<array{
     *     composite_image_url: string,
     *     style_label?: string|null,
     *     style_key?: string|null,
     *     sort_order?: int,
     *     provider_meta?: array<string, mixed>|null
     * }>  $previewRows
     */
    public function markReady(AiHairstyleSession $session, array $previewRows): AiHairstyleSession
    {
        $this->assertTenant($session);

        if ($previewRows === []) {
            throw ValidationException::withMessages([
                'previews' => ['At least one composite preview is required.'],
            ]);
        }

        return DB::transaction(function () use ($session, $previewRows) {
            $this->transition($session, AiHairstyleStatuses::SESSION_READY, [
                'error_message' => null,
            ]);

            AiHairstylePreview::query()
                ->where('session_id', $session->id)
                ->delete();

            foreach (array_values($previewRows) as $index => $row) {
                $url = trim((string) ($row['composite_image_url'] ?? ''));
                if ($url === '') {
                    throw ValidationException::withMessages([
                        'previews' => ['Each preview must include a composite_image_url.'],
                    ]);
                }

                AiHairstylePreview::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'session_id' => $session->id,
                    'status' => AiHairstyleStatuses::PREVIEW_READY,
                    'composite_image_url' => $url,
                    'style_label' => $row['style_label'] ?? null,
                    'style_key' => $row['style_key'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                    'provider_meta' => $row['provider_meta'] ?? null,
                ]);
            }

            $this->auditLogger->log('ai_hairstyle.session.ready', $session, null, [
                'preview_count' => count($previewRows),
            ]);

            return $session->fresh(['previews']);
        });
    }

    public function markFailed(AiHairstyleSession $session, string $message): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_FAILED, [
            'error_message' => mb_substr(trim($message), 0, 1000) ?: 'Generation failed.',
        ]);

        return $session->fresh(['previews']);
    }

    /**
     * @param  list<string>  $previewIds
     */
    public function selectPreviews(AiHairstyleSession $session, array $previewIds): AiHairstyleSession
    {
        $this->assertTenant($session);

        $previewIds = array_values(array_unique(array_filter($previewIds, fn ($id) => is_string($id) && $id !== '')));
        if ($previewIds === []) {
            throw ValidationException::withMessages([
                'preview_ids' => ['Select at least one preview.'],
            ]);
        }

        $owned = AiHairstylePreview::query()
            ->where('session_id', $session->id)
            ->where('status', AiHairstyleStatuses::PREVIEW_READY)
            ->whereIn('id', $previewIds)
            ->pluck('id')
            ->all();

        if (count($owned) !== count($previewIds)) {
            throw ValidationException::withMessages([
                'preview_ids' => ['One or more previews are invalid for this session.'],
            ]);
        }

        $from = $session->status;
        if ($from === AiHairstyleStatuses::SESSION_SELECTED) {
            // Allow re-select while still selected (stay selected).
            $old = $session->only(['status', 'selected_preview_ids']);
            $session->forceFill(['selected_preview_ids' => $previewIds])->save();
            $this->auditLogger->log('ai_hairstyle.session.selected', $session, $old, [
                'status' => $session->status,
                'selected_preview_ids' => $previewIds,
            ]);

            return $session->fresh(['previews']);
        }

        $this->transition($session, AiHairstyleStatuses::SESSION_SELECTED, [
            'selected_preview_ids' => $previewIds,
        ]);

        return $session->fresh(['previews']);
    }

    public function clearSelection(AiHairstyleSession $session): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_READY, [
            'selected_preview_ids' => null,
        ]);

        return $session->fresh(['previews']);
    }

    public function submit(AiHairstyleSession $session): AiHairstyleSession
    {
        $this->assertTenant($session);

        $selected = $session->selected_preview_ids ?? [];
        if (! is_array($selected) || $selected === []) {
            throw ValidationException::withMessages([
                'selected_preview_ids' => ['Select a look before submitting.'],
            ]);
        }

        $this->transition($session, AiHairstyleStatuses::SESSION_SUBMITTED, [
            'submitted_at' => now(),
        ]);

        return $session->fresh(['previews']);
    }

    public function accept(AiHairstyleSession $session, ?int $acceptedByUserId = null): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_ACCEPTED, [
            'accepted_at' => now(),
            'accepted_by_user_id' => $acceptedByUserId,
        ]);

        return $session->fresh(['previews']);
    }

    public function cancel(AiHairstyleSession $session): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_CANCELLED);

        return $session->fresh(['previews']);
    }

    public function expire(AiHairstyleSession $session): AiHairstyleSession
    {
        $this->assertTenant($session);
        $this->transition($session, AiHairstyleStatuses::SESSION_EXPIRED);

        return $session->fresh(['previews']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(AiHairstyleSession $session, string $to, array $attributes = []): void
    {
        $from = (string) $session->status;
        if (! AiHairstyleStatuses::canTransitionSession($from, $to)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition AI hairstyle session from {$from} to {$to}."],
            ]);
        }

        $old = $session->only([
            'status',
            'selected_preview_ids',
            'error_message',
            'submitted_at',
            'accepted_at',
            'accepted_by_user_id',
        ]);

        $session->forceFill(array_merge(['status' => $to], $attributes))->save();

        $this->auditLogger->log('ai_hairstyle.session.status_changed', $session, $old, [
            'status' => $to,
            'selected_preview_ids' => $session->selected_preview_ids,
            'error_message' => $session->error_message,
            'submitted_at' => $session->submitted_at,
            'accepted_at' => $session->accepted_at,
            'accepted_by_user_id' => $session->accepted_by_user_id,
        ]);
    }

    private function assertTenant(AiHairstyleSession $session): void
    {
        if ($session->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages(['resource' => ['Resource not found.']]);
        }
    }

    private function assertClientBelongsToTenant(string $clientId, string $tenantId): void
    {
        $exists = Client::withoutGlobalScopes()
            ->where('id', $clientId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'client_id' => ['Client not found for this tenant.'],
            ]);
        }
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages(['tenant' => ['Tenant context is required.']]);
        }

        return $id;
    }
}
