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
     * @return array{visit: ClientVisit, points: int, already_checked_in_today: bool}
     */
    public function checkInFromMember(Client $client, ?string $locationId = null): array
    {
        $this->assertTenantClient($client);

        if ($locationId !== null) {
            $this->assertLocation($locationId);
        }

        $timezone = $this->resolveTimezone($client, $locationId);
        $dayStart = Carbon::now($timezone)->startOfDay()->utc();
        $dayEnd = Carbon::now($timezone)->endOfDay()->utc();

        $existing = ClientVisit::query()
            ->where('client_id', $client->id)
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->orderByDesc('checked_in_at')
            ->first();

        if ($existing !== null) {
            return [
                'visit' => $existing->load('location'),
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
