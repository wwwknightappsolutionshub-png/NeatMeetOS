<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Services\ProgressiveModuleAccessService;
use App\Domains\Marketing\Services\MarketingAutomationTriggerService;
use App\Domains\Marketing\Services\MarketingWelcomeAutomationService;
use App\Domains\Memberships\Enums\ClientMembershipStatus;
use App\Shared\Audit\AuditLogger;
use App\Shared\Support\PhoneNormalizer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ClientService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
        private readonly ClientTimelineService $timelineService,
        private readonly MarketingAutomationTriggerService $marketingTriggers,
        private readonly MarketingWelcomeAutomationService $welcomeAutomation,
        private readonly ProgressiveModuleAccessService $progressiveAccess,
    ) {}

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Client::query()
            ->with([
                'tags',
                'primaryLocation',
                'memberships' => function ($q) {
                    $q->whereIn('status', [
                        ClientMembershipStatus::ACTIVE,
                        ClientMembershipStatus::TRIALING,
                    ])
                        ->with('membershipPlan')
                        ->orderByDesc('started_at');
                },
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('display_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        if (! empty($filters['primary_location_id'])) {
            $query->where('primary_location_id', $filters['primary_location_id']);
        }

        if (! empty($filters['tag_ids'])) {
            $tagIds = is_array($filters['tag_ids']) ? $filters['tag_ids'] : explode(',', $filters['tag_ids']);
            $query->whereHas('tags', fn ($q) => $q->whereIn('client_tags.id', $tagIds));
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): Client
    {
        return Client::query()
            ->with([
                'tags',
                'primaryLocation',
                'preferredTeamMember',
                'memberships' => function ($q) {
                    $q->whereIn('status', [
                        ClientMembershipStatus::ACTIVE,
                        ClientMembershipStatus::TRIALING,
                    ])
                        ->with('membershipPlan')
                        ->orderByDesc('started_at');
                },
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{skip_automations?: bool}  $options
     */
    public function create(array $data, array $options = []): Client
    {
        $tenantId = $this->requireTenantId();
        $this->validateLocation($data['primary_location_id'] ?? null, $tenantId);
        $this->validateTeamMember($data['preferred_team_member_id'] ?? null, $tenantId);
        $this->assertUniquePhone($data['phone'] ?? null);

        $data['tenant_id'] = $tenantId;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['display_name'] = $data['display_name'] ?? null;
        $data['first_name'] = $this->nullableTrimmedString($data['first_name'] ?? null);
        $data['last_name'] = $this->nullableTrimmedString($data['last_name'] ?? null);
        $data['phone'] = PhoneNormalizer::normalize($data['phone'] ?? null) ?: null;

        $client = Client::query()->create($data);

        $this->auditLogger->log('client.created', $client, null, $client->only([
            'first_name', 'last_name', 'email', 'phone',
        ]));

        $this->timelineService->record(
            $client,
            ClientTimelineEvent::EVENT_CLIENT_CREATED,
            'Client profile created',
            $client->resolvedDisplayName(),
        );

        if (! ($options['skip_automations'] ?? false)) {
            try {
                $this->marketingTriggers->fireClientCreated($client);
            } catch (\Throwable) {
                // Marketing automations must not block CRM client creation.
            }

            try {
                $this->welcomeAutomation->queueWelcomeEmail($client, 15);
            } catch (\Throwable) {
                // Welcome email queue must not block CRM client creation.
            }

            try {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant) {
                    $this->progressiveAccess->maybeNudgeAfterClientCreated($tenant);
                }
            } catch (\Throwable) {
                // Upgrade nudges must not block CRM client creation.
            }
        }

        return $client->fresh(['tags', 'primaryLocation']);
    }

    public function update(Client $client, array $data): Client
    {
        $this->assertTenantClient($client);

        if (array_key_exists('primary_location_id', $data)) {
            $this->validateLocation($data['primary_location_id'], $client->tenant_id);
        }

        if (array_key_exists('preferred_team_member_id', $data)) {
            $this->validateTeamMember($data['preferred_team_member_id'], $client->tenant_id);
        }

        if (array_key_exists('first_name', $data)) {
            $data['first_name'] = $this->nullableTrimmedString($data['first_name']);
        }
        if (array_key_exists('last_name', $data)) {
            $data['last_name'] = $this->nullableTrimmedString($data['last_name']);
        }
        if (array_key_exists('phone', $data)) {
            $this->assertUniquePhone($data['phone'], $client->id);
            $data['phone'] = PhoneNormalizer::normalize($data['phone']) ?: null;
        }

        $preferenceFields = array_intersect_key($data, array_flip(['preferences', 'loyalty_display_status', 'internal_flags']));
        $hasPreferenceChange = $preferenceFields !== [];

        $old = $client->only(array_keys($data));
        $client->fill($data);
        $client->save();

        $this->auditLogger->log('client.updated', $client, $old, $client->only(array_keys($data)));

        if ($hasPreferenceChange) {
            $this->timelineService->record(
                $client,
                ClientTimelineEvent::EVENT_PROFILE_PREFERENCES_UPDATED,
                'Client preferences updated',
                null,
                ['fields' => array_keys($preferenceFields)],
            );
        } else {
            $this->timelineService->record(
                $client,
                ClientTimelineEvent::EVENT_CLIENT_UPDATED,
                'Client profile updated',
                null,
                ['fields' => array_keys($data)],
            );
        }

        return $client->fresh(['tags', 'primaryLocation', 'preferredTeamMember']);
    }

    public function setActive(Client $client, bool $isActive): Client
    {
        $this->assertTenantClient($client);

        $old = ['is_active' => $client->is_active];
        $client->is_active = $isActive;
        $client->save();

        $action = $isActive ? 'client.activated' : 'client.deactivated';
        $this->auditLogger->log($action, $client, $old, ['is_active' => $isActive]);

        $this->timelineService->record(
            $client,
            $isActive ? ClientTimelineEvent::EVENT_CLIENT_ACTIVATED : ClientTimelineEvent::EVENT_CLIENT_DEACTIVATED,
            $isActive ? 'Client reactivated' : 'Client archived',
        );

        return $client->fresh(['tags', 'primaryLocation']);
    }

    private function validateTeamMember(?string $teamMemberId, string $tenantId): void
    {
        if ($teamMemberId === null) {
            return;
        }

        $valid = TeamMember::query()
            ->where('id', $teamMemberId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'preferred_team_member_id' => ['Team member does not belong to this tenant.'],
            ]);
        }
    }

    private function validateLocation(?string $locationId, string $tenantId): void
    {
        if ($locationId === null) {
            return;
        }

        $valid = Location::query()
            ->where('id', $locationId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'primary_location_id' => ['Location does not belong to this tenant.'],
            ]);
        }
    }

    private function assertUniquePhone(?string $phone, ?string $exceptClientId = null): void
    {
        if (! PhoneNormalizer::isValid($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['A valid phone number is required.'],
            ]);
        }

        $normalized = PhoneNormalizer::normalize($phone);
        $query = Client::query()->where('phone_normalized', $normalized);
        if ($exceptClientId !== null) {
            $query->where('id', '!=', $exceptClientId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['A client with this phone number already exists.'],
            ]);
        }
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages([
                'client' => ['Client not found.'],
            ]);
        }
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $tenantId;
    }
}
