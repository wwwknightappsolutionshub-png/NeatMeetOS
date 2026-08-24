<?php

namespace App\Domains\Crm\Services;

use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Models\ClientTimelineEvent;
use App\Domains\Crm\Models\ClientVisit;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Memberships\Enums\LoyaltyEntryDirection;
use App\Domains\Memberships\Enums\LoyaltyEntryType;
use App\Domains\Memberships\Services\LoyaltyLedgerService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientVisitService
{
    public const CHECKIN_LOYALTY_POINTS = 10;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LoyaltyLedgerService $loyaltyLedger,
        private readonly ClientTimelineService $timeline,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function listForClient(string $clientId): Collection
    {
        $this->findClient($clientId);

        return ClientVisit::query()
            ->with('location')
            ->where('client_id', $clientId)
            ->orderByDesc('checked_in_at')
            ->limit(200)
            ->get();
    }

    /**
     * Open visits (checked in, not checked out) for the current tenant.
     *
     * @return Collection<int, ClientVisit>
     */
    public function listOpenVisits(?string $locationId = null): Collection
    {
        $query = ClientVisit::query()
            ->with(['client', 'location'])
            ->whereNull('checked_out_at')
            ->orderByDesc('checked_in_at')
            ->limit(200);

        if ($locationId !== null) {
            $this->assertLocation($locationId);
            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    public function openVisitForClient(Client $client): ?ClientVisit
    {
        $this->assertTenantClient($client);

        return ClientVisit::query()
            ->with('location')
            ->where('client_id', $client->id)
            ->whereNull('checked_out_at')
            ->orderByDesc('checked_in_at')
            ->first();
    }

    /**
     * @return array{visit: ClientVisit, points: int, already_checked_in_today: bool}
     */
    public function checkInFromMember(Client $client, ?string $locationId = null): array
    {
        $this->assertTenantClient($client);

        if ($locationId !== null) {
            $this->assertLocation($locationId);
        }

        $open = $this->openVisitForClient($client);
        if ($open !== null) {
            return [
                'visit' => $open,
                'points' => 0,
                'already_checked_in_today' => true,
            ];
        }

        $timezone = $this->resolveTimezone($client, $locationId);
        $dayStart = Carbon::now($timezone)->startOfDay()->utc();
        $dayEnd = Carbon::now($timezone)->endOfDay()->utc();

        $existingToday = ClientVisit::query()
            ->with('location')
            ->where('client_id', $client->id)
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->orderByDesc('checked_in_at')
            ->first();

        // One presence visit per local day (after clock-out, do not open another).
        if ($existingToday !== null) {
            return [
                'visit' => $existingToday,
                'points' => 0,
                'already_checked_in_today' => true,
            ];
        }

        $result = DB::transaction(function () use ($client, $locationId) {
            $checkedInAt = now();
            $points = self::CHECKIN_LOYALTY_POINTS;

            $visit = ClientVisit::query()->create([
                'tenant_id' => $this->requireTenantId(),
                'client_id' => $client->id,
                'location_id' => $locationId,
                'checked_in_at' => $checkedInAt,
                'source' => 'member_app',
                'loyalty_points_awarded' => $points,
            ]);

            if ($points > 0) {
                $this->loyaltyLedger->postEntry([
                    'client_id' => $client->id,
                    'entry_type' => LoyaltyEntryType::CHECKIN_VISIT,
                    'direction' => LoyaltyEntryDirection::CREDIT,
                    'points' => $points,
                    'effective_at' => $checkedInAt,
                    'source_type' => 'client_visit',
                    'source_id' => $visit->id,
                    'notes' => 'Member app visit check-in',
                ]);
            }

            $client->last_visited_at = $checkedInAt;
            $client->save();

            $this->timeline->record(
                $client->fresh(),
                ClientTimelineEvent::EVENT_VISIT_CHECKIN,
                'Visit check-in',
                'Checked in via membership app',
                [
                    'visit_id' => $visit->id,
                    'location_id' => $locationId,
                    'loyalty_points_awarded' => $points,
                ],
            );

            $this->auditLogger->log('visit.checkin', $visit, null, [
                'client_id' => $client->id,
                'location_id' => $locationId,
                'loyalty_points_awarded' => $points,
            ]);

            return [
                'visit' => $visit->load('location'),
                'points' => $points,
                'already_checked_in_today' => false,
            ];
        });

        return $result;
    }

    /**
     * @return array{visit: ClientVisit}
     */
    public function checkOutFromMember(Client $client): array
    {
        $this->assertTenantClient($client);

        $open = $this->openVisitForClient($client);
        if ($open === null) {
            throw ValidationException::withMessages([
                'visit' => ['You are not checked in.'],
            ]);
        }

        $open->checked_out_at = now();
        $open->save();

        $this->timeline->record(
            $client->fresh(),
            ClientTimelineEvent::EVENT_VISIT_CHECKOUT,
            'Visit check-out',
            'Checked out via membership app',
            [
                'visit_id' => $open->id,
                'checked_out_at' => $open->checked_out_at?->toIso8601String(),
            ],
        );

        $this->auditLogger->log('visit.checkout', $open, null, [
            'client_id' => $client->id,
            'checked_out_at' => $open->checked_out_at?->toIso8601String(),
        ]);

        return ['visit' => $open->fresh()->load('location')];
    }

    public function hasCheckedInToday(Client $client, ?string $locationId = null): bool
    {
        $timezone = $this->resolveTimezone($client, $locationId);
        $dayStart = Carbon::now($timezone)->startOfDay()->utc();
        $dayEnd = Carbon::now($timezone)->endOfDay()->utc();

        return ClientVisit::query()
            ->where('client_id', $client->id)
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeVisit(ClientVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'client_id' => $visit->client_id,
            'location_id' => $visit->location_id,
            'location' => $visit->relationLoaded('location') && $visit->location
                ? ['id' => $visit->location->id, 'name' => $visit->location->name]
                : null,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
            'checked_out_at' => $visit->checked_out_at?->toIso8601String(),
            'source' => $visit->source,
            'loyalty_points_awarded' => $visit->loyalty_points_awarded,
            'next_visit_appointment_id' => $visit->next_visit_appointment_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOpenVisit(ClientVisit $visit): array
    {
        $client = $visit->client;

        return [
            'id' => $visit->id,
            'client_id' => $visit->client_id,
            'client' => $client ? [
                'id' => $client->id,
                'display_name' => $client->display_name,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'resolved_display_name' => method_exists($client, 'resolvedDisplayName')
                    ? $client->resolvedDisplayName()
                    : trim(($client->display_name ?: $client->first_name).' '.($client->last_name ?? '')),
                'phone' => $client->phone,
                'email' => $client->email,
            ] : null,
            'location_id' => $visit->location_id,
            'location' => $visit->location ? [
                'id' => $visit->location->id,
                'name' => $visit->location->name,
            ] : null,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
            'source' => $visit->source,
            'loyalty_points_awarded' => $visit->loyalty_points_awarded,
        ];
    }

    private function resolveTimezone(Client $client, ?string $locationId): string
    {
        if ($locationId !== null) {
            $location = Location::query()->find($locationId);
            if ($location?->timezone) {
                return $location->timezone;
            }
        }

        if ($client->primary_location_id) {
            $primary = Location::query()->find($client->primary_location_id);
            if ($primary?->timezone) {
                return $primary->timezone;
            }
        }

        $tenant = Tenant::query()->find($this->requireTenantId());

        return $tenant?->timezone ?: 'Europe/London';
    }

    private function findClient(string $clientId): Client
    {
        $client = Client::query()->findOrFail($clientId);
        $this->assertTenantClient($client);

        return $client;
    }

    private function assertTenantClient(Client $client): void
    {
        if ($client->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages([
                'client' => ['Client not found.'],
            ]);
        }
    }

    private function assertLocation(string $locationId): void
    {
        $ok = Location::query()
            ->where('id', $locationId)
            ->where('is_active', true)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'location_id' => ['Location not found for this salon.'],
            ]);
        }
    }

    private function requireTenantId(): string
    {
        $id = $this->tenantContext->id();
        if ($id === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Tenant context is required.'],
            ]);
        }

        return $id;
    }
}
