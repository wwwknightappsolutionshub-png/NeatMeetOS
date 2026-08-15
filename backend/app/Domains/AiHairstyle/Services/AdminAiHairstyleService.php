<?php

namespace App\Domains\AiHairstyle\Services;

use App\Domains\AiHairstyle\Mail\AiHairstyleLookAcceptedMail;
use App\Domains\AiHairstyle\Mail\AiHairstyleLookDeclinedMail;
use App\Domains\AiHairstyle\Models\AiHairstyleSession;
use App\Domains\AiHairstyle\Support\AiHairstyleStatuses;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientNotice;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Crm\Services\ClientNoticeService;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\ClientTimelineService;
use App\Domains\Identity\Services\TenantEntitlementService;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminAiHairstyleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantEntitlementService $entitlements,
        private readonly AiHairstyleSessionService $sessions,
        private readonly ClientService $clients,
        private readonly ClientNoticeService $notices,
        private readonly ClientTimelineService $timeline,
    ) {}

    /**
     * Customer-approved looks awaiting salonist review.
     *
     * @return Collection<int, AiHairstyleSession>
     */
    public function listSubmitted(int $limit = 100): Collection
    {
        $this->assertModuleEnabled();

        return $this->sessions->listByStatus(AiHairstyleStatuses::SESSION_SUBMITTED, $limit);
    }

    public function accept(string $sessionId, ?int $acceptedByUserId = null): AiHairstyleSession
    {
        $this->assertModuleEnabled();

        return DB::transaction(function () use ($sessionId, $acceptedByUserId) {
            $session = $this->sessions->find($sessionId);

            if ($session->status !== AiHairstyleStatuses::SESSION_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => ['Only submitted looks can be accepted.'],
                ]);
            }

            $client = $this->resolveClientForSession($session);
            if ($session->client_id !== $client->id) {
                $session->forceFill(['client_id' => $client->id])->save();
                $session = $session->fresh(['previews', 'client']);
            }

            $session = $this->sessions->accept($session, $acceptedByUserId);

            $selectedLabels = $session->previews
                ->whereIn('id', $session->selected_preview_ids ?? [])
                ->pluck('style_label')
                ->filter()
                ->values()
                ->all();
            $lookLabel = $selectedLabels !== []
                ? implode(', ', $selectedLabels)
                : 'your selected look';

            $this->notices->createForClient($client, [
                'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
                'title' => 'Your look was approved',
                'body' => "Great news — the salon accepted {$lookLabel}. See you soon.",
                'href' => null,
                'data' => [
                    'ai_hairstyle_session_id' => $session->id,
                    'selected_preview_ids' => $session->selected_preview_ids ?? [],
                ],
            ]);

            $clientForMail = $client;
            $labelForMail = $lookLabel;
            DB::afterCommit(function () use ($clientForMail, $labelForMail) {
                $this->emailClientAcceptance($clientForMail, $labelForMail);
            });

            $this->timeline->record(
                $client,
                ClientTimelineEvent::EVENT_AI_HAIRSTYLE_ACCEPTED,
                'AI look accepted',
                'Salonist accepted the customer-approved AI hairstyle preview.',
                [
                    'session_id' => $session->id,
                    'selected_preview_ids' => $session->selected_preview_ids ?? [],
                ],
                $acceptedByUserId,
            );

            return $session->fresh(['previews', 'client']);
        });
    }

    public function decline(string $sessionId, ?int $declinedByUserId = null): AiHairstyleSession
    {
        $this->assertModuleEnabled();

        return DB::transaction(function () use ($sessionId, $declinedByUserId) {
            $session = $this->sessions->find($sessionId);

            if ($session->status !== AiHairstyleStatuses::SESSION_SUBMITTED) {
                throw ValidationException::withMessages([
                    'status' => ['Only submitted looks can be declined.'],
                ]);
            }

            $client = $this->resolveClientForSession($session);
            if ($session->client_id !== $client->id) {
                $session->forceFill(['client_id' => $client->id])->save();
                $session = $session->fresh(['previews', 'client']);
            }

            $session = $this->sessions->cancel($session);

            $this->notices->createForClient($client, [
                'type' => ClientNotice::TYPE_OPERATIONAL_IN_APP,
                'title' => 'Your look was not approved',
                'body' => 'The salon reviewed your AI look and is not moving forward with it this time. Try another look when you book.',
                'href' => null,
                'data' => [
                    'ai_hairstyle_session_id' => $session->id,
                    'selected_preview_ids' => $session->selected_preview_ids ?? [],
                ],
            ]);

            $clientForMail = $client;
            DB::afterCommit(function () use ($clientForMail) {
                $this->emailClientDecline($clientForMail);
            });

            $this->timeline->record(
                $client,
                ClientTimelineEvent::EVENT_AI_HAIRSTYLE_DECLINED,
                'AI look declined',
                'Salonist declined the customer-approved AI hairstyle preview.',
                [
                    'session_id' => $session->id,
                    'selected_preview_ids' => $session->selected_preview_ids ?? [],
                ],
                $declinedByUserId,
            );

            return $session->fresh(['previews', 'client']);
        });
    }

    /**
     * Admin payload — composites and contact only; never includes original face.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(AiHairstyleSession $session): array
    {
        $contact = is_array($session->metadata['contact'] ?? null)
            ? $session->metadata['contact']
            : null;

        $selected = collect($session->selected_preview_ids ?? []);

        return [
            'id' => $session->id,
            'status' => $session->status,
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'accepted_at' => $session->accepted_at?->toIso8601String(),
            'client' => $session->client ? [
                'id' => $session->client->id,
                'display_name' => $session->client->resolvedDisplayName(),
                'email' => $session->client->email,
                'phone' => $session->client->phone,
            ] : null,
            'contact' => $contact ? [
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'notes' => $contact['notes'] ?? null,
            ] : null,
            'selected_previews' => $session->previews
                ->filter(fn ($p) => $selected->contains($p->id))
                ->values()
                ->map(fn ($preview) => [
                    'id' => $preview->id,
                    'composite_image_url' => PublicStorageUrl::normalize($preview->composite_image_url),
                    'style_label' => $preview->style_label,
                    'style_key' => $preview->style_key,
                    'sort_order' => $preview->sort_order,
                ])
                ->all(),
        ];
    }

    private function emailClientAcceptance(Client $client, string $lookLabel): void
    {
        $email = trim((string) ($client->email ?? ''));
        if ($email === '') {
            return;
        }

        $tenant = $this->tenantContext->get();
        $salon = $tenant?->name ?? 'your salon';
        $firstName = trim((string) ($client->first_name ?? '')) ?: 'there';

        try {
            Mail::to($email)->queue(new AiHairstyleLookAcceptedMail(
                salonName: $salon,
                guestFirstName: $firstName,
                lookLabel: $lookLabel,
            ));
        } catch (\Throwable) {
            // Delivery must never block accept.
        }
    }

    private function emailClientDecline(Client $client): void
    {
        $email = trim((string) ($client->email ?? ''));
        if ($email === '') {
            return;
        }

        $tenant = $this->tenantContext->get();
        $salon = $tenant?->name ?? 'your salon';
        $firstName = trim((string) ($client->first_name ?? '')) ?: 'there';

        try {
            Mail::to($email)->queue(new AiHairstyleLookDeclinedMail(
                salonName: $salon,
                guestFirstName: $firstName,
            ));
        } catch (\Throwable) {
            // Delivery must never block decline.
        }
    }

    private function resolveClientForSession(AiHairstyleSession $session): Client
    {
        if ($session->client_id) {
            return Client::query()->findOrFail($session->client_id);
        }

        $contact = is_array($session->metadata['contact'] ?? null)
            ? $session->metadata['contact']
            : [];
        $phone = PhoneNormalizer::normalize($contact['phone'] ?? null);
        if (! PhoneNormalizer::isValid($phone)) {
            throw ValidationException::withMessages([
                'client' => ['This look has no customer phone number to notify.'],
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

    private function assertModuleEnabled(): void
    {
        $tenant = $this->tenantContext->get();
        if (! $this->entitlements->isEnabled($tenant, 'ai_hairstyle')) {
            throw ValidationException::withMessages([
                'module' => ['AI Hairstyle Preview is not available on this plan.'],
            ]);
        }
    }
}
