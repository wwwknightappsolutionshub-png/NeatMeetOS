<?php

namespace App\Domains\AiHairstyle\Services;

use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\ClientTimelineService;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\TenantOwnerNotice;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Jobs\GenerateAiHairstyleJob;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicAiHairstyleFlowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantEntitlementService $entitlements,
        private readonly AiHairstyleSessionService $sessions,
        private readonly AiHairstyleProviderResolver $providers,
        private readonly ClientService $clients,
        private readonly ClientTimelineService $timeline,
    ) {}

    public function createSession(): AiHairstyleSession
    {
        $this->assertModuleEnabled();

        $session = $this->sessions->createDraft([
            'expires_at' => now()->addDay(),
            'metadata' => ['channel' => 'public_book'],
        ]);

        $session->forceFill([
            'public_token' => Str::random(48),
        ])->save();

        return $session->fresh(['previews']);
    }

    public function show(string $sessionId, string $publicToken, bool $withPreviews = true): AiHairstyleSession
    {
        $this->assertModuleEnabled();

        return $this->locatePublicSession($sessionId, $publicToken, $withPreviews);
    }

    public function generate(string $sessionId, string $publicToken, UploadedFile $selfie): AiHairstyleSession
    {
        $this->assertModuleEnabled();
        $session = $this->locatePublicSession($sessionId, $publicToken);

        if ($session->status === AiHairstyleStatuses::SESSION_GENERATING) {
            $session = $this->releaseStaleGeneratingOrReject($session);
        }

        if (! in_array($session->status, [
            AiHairstyleStatuses::SESSION_DRAFT,
            AiHairstyleStatuses::SESSION_FAILED,
            AiHairstyleStatuses::SESSION_READY,
            AiHairstyleStatuses::SESSION_SELECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['This look session cannot generate previews in its current state.'],
            ]);
        }

        if ($session->status === AiHairstyleStatuses::SESSION_SELECTED) {
            $session = $this->sessions->clearSelection($session);
        }

        $this->providers->assertGenerationAllowed();
        $providerName = $this->providers->activeName();
        $session = $this->sessions->markGenerating($session, $providerName);

        $tempRelative = $this->storeEphemeralSelfie($session, $selfie);
        $disk = (string) config('ai_hairstyle.temp_disk', 'local');

        try {
            GenerateAiHairstyleJob::dispatch(
                (string) $session->tenant_id,
                (string) $session->id,
                $tempRelative,
            );
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($tempRelative);
            $this->sessions->markFailed(
                $session->fresh() ?? $session,
                'Could not start look generation. Please try again.',
            );
            Log::error('ai_hairstyle.dispatch_failed', [
                'tenant_id' => $session->tenant_id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'generation' => ['Could not start look generation. Please try again.'],
            ]);
        }

        return $session->fresh(['previews']);
    }

    /**
     * @param  list<string>  $previewIds
     */
    public function select(string $sessionId, string $publicToken, array $previewIds): AiHairstyleSession
    {
        $this->assertModuleEnabled();
        $session = $this->locatePublicSession($sessionId, $publicToken);

        return $this->sessions->selectPreviews($session, $previewIds);
    }

    /**
     * @param  array{first_name?: string|null, last_name?: string|null, email?: string|null, phone?: string|null, notes?: string|null}  $contact
     */
    public function submit(string $sessionId, string $publicToken, array $contact = []): AiHairstyleSession
    {
        $this->assertModuleEnabled();
        $session = $this->locatePublicSession($sessionId, $publicToken);

        $meta = is_array($session->metadata) ? $session->metadata : [];
        $meta['contact'] = [
            'first_name' => $contact['first_name'] ?? null,
            'last_name' => $contact['last_name'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'notes' => $contact['notes'] ?? null,
        ];

        $client = $this->resolveOrCreateClient($meta['contact']);

        $session->forceFill([
            'metadata' => $meta,
            'client_id' => $client->id,
        ])->save();

        $session = $this->sessions->submit($session->fresh(['previews']));

        $this->timeline->record(
            $client,
            ClientTimelineEvent::EVENT_AI_HAIRSTYLE_SUBMITTED,
            'AI look submitted',
            'Customer submitted an AI hairstyle look for salon review.',
            [
                'session_id' => $session->id,
                'selected_preview_ids' => $session->selected_preview_ids ?? [],
            ],
            null,
        );

        $this->notifyOwnersOfSubmission($client, $session);

        return $session->fresh(['previews']);
    }

    private function releaseStaleGeneratingOrReject(AiHairstyleSession $session): AiHairstyleSession
    {
        $staleMinutes = max(1, (int) config('ai_hairstyle.stale_generating_minutes', 7));
        $updatedAt = $session->updated_at;

        if ($updatedAt !== null && $updatedAt->gt(now()->subMinutes($staleMinutes))) {
            throw ValidationException::withMessages([
                'status' => ['Looks are still generating. Please wait a moment, then try again.'],
            ]);
        }

        return $this->sessions->markFailed(
            $session,
            'Previous generation timed out. Please try again with a new selfie.',
        );
    }

    /**
     * @param  array{first_name?: string|null, last_name?: string|null, email?: string|null, phone?: string|null}  $contact
     */
    private function resolveOrCreateClient(array $contact): Client
    {
        $phone = PhoneNormalizer::normalize($contact['phone'] ?? null);
        if (! PhoneNormalizer::isValid($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['A valid phone number is required.'],
            ]);
        }

        $email = isset($contact['email']) && filled($contact['email'])
            ? strtolower(trim((string) $contact['email']))
            : null;
        $firstName = isset($contact['first_name']) ? trim((string) $contact['first_name']) : '';
        $lastName = isset($contact['last_name']) ? trim((string) $contact['last_name']) : '';

        $existing = Client::query()
            ->where('phone_normalized', $phone)
            ->first();
        if ($existing !== null) {
            $updates = [];
            if ($email !== null && empty($existing->email)) {
                $updates['email'] = $email;
            }
            if ($firstName !== '' && empty($existing->first_name)) {
                $updates['first_name'] = $firstName;
            }
            if ($lastName !== '' && empty($existing->last_name)) {
                $updates['last_name'] = $lastName;
            }
            if ($updates !== []) {
                return $this->clients->update($existing, $updates);
            }

            return $existing;
        }

        return $this->clients->create([
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'phone' => $phone,
            'display_name' => trim($firstName.' '.$lastName) ?: null,
        ], ['skip_automations' => true]);
    }

    private function notifyOwnersOfSubmission(Client $client, AiHairstyleSession $session): void
    {
        $tenant = $this->tenantContext->get();
        if ($tenant === null) {
            return;
        }

        $owners = TeamMember::query()
            ->where('employment_type', TeamMember::EMPLOYMENT_OWNER)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->get();

        $clientName = $client->resolvedDisplayName() ?: 'A client';

        foreach ($owners as $owner) {
            TenantOwnerNotice::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->user_id,
                'type' => 'ai_hairstyle.submitted',
                'title' => 'New AI look submitted',
                'body' => $clientName.' submitted an AI hairstyle look for review.',
                'href' => '/admin/ai-hairstyle',
                'data' => [
                    'ai_hairstyle_session_id' => $session->id,
                    'client_id' => $client->id,
                ],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(AiHairstyleSession $session, bool $lite = false): array
    {
        $payload = [
            'id' => $session->id,
            'public_token' => $session->public_token,
            'status' => $session->status,
            'error_message' => $session->error_message,
        ];

        if ($lite) {
            return $payload;
        }

        return array_merge($payload, [
            'selected_preview_ids' => $session->selected_preview_ids ?? [],
            'provider' => $session->provider,
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'previews' => $session->previews->map(fn ($preview) => [
                'id' => $preview->id,
                'status' => $preview->status,
                'composite_image_url' => PublicStorageUrl::normalize($preview->composite_image_url),
                'style_label' => $preview->style_label,
                'style_key' => $preview->style_key,
                'sort_order' => $preview->sort_order,
            ])->values()->all(),
        ]);
    }

    private function storeEphemeralSelfie(AiHairstyleSession $session, UploadedFile $selfie): string
    {
        $disk = (string) config('ai_hairstyle.temp_disk', 'local');
        $prefix = trim((string) config('ai_hairstyle.temp_prefix', 'ai_hairstyle_tmp'), '/');
        $ext = strtolower($selfie->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $relative = sprintf('%s/%s/%s.%s', $prefix, $session->tenant_id, $session->id, $ext);
        Storage::disk($disk)->put($relative, file_get_contents($selfie->getRealPath()) ?: '');

        return $relative;
    }

    private function locatePublicSession(
        string $sessionId,
        string $publicToken,
        bool $withPreviews = true,
    ): AiHairstyleSession
    {
        $session = $this->sessions->find($sessionId);

        if (! hash_equals((string) $session->public_token, $publicToken)) {
            throw ValidationException::withMessages([
                'public_token' => ['Invalid look session token.'],
            ]);
        }

        if ($session->expires_at !== null && $session->expires_at->isPast()) {
            if (! $session->isTerminal()) {
                $this->sessions->expire($session);
            }
            throw ValidationException::withMessages([
                'session' => ['This look session has expired.'],
            ]);
        }

        return $withPreviews ? $session->fresh(['previews']) : $session->fresh();
    }

    private function assertModuleEnabled(): void
    {
        $tenant = $this->tenantContext->get();
        if (! $this->entitlements->isEnabled($tenant, 'ai_hairstyle')) {
            throw ValidationException::withMessages([
                'module' => ['AI Hairstyle Preview is not available for this salon.'],
            ]);
        }
    }
}
