<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Crm\Models\Client;
use App\Domains\Crm\Services\ClientService;
use App\Domains\Crm\Services\MemberPortalAuthService;
use App\Domains\Identity\Models\Location;
use App\Domains\Identity\Models\TeamMember;
use App\Domains\Staff\Models\StaffAvailabilityRule;
use App\Domains\Staff\Models\StaffProfile;
use App\Shared\Tenancy\TenantContext;
use App\Shared\Support\PublicStorageUrl;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Public online booking orchestration — reuses AppointmentBookingService + scheduling rules.
 */
class OnlineBookingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $scope,
        private readonly AppointmentBookingService $appointments,
        private readonly AppointmentSchedulingValidator $schedulingValidator,
        private readonly ClientService $clients,
        private readonly MemberPortalAuthService $memberPortal,
    ) {}

    /**
     * @return array{tenant: array<string, mixed>, locations: Collection, services: Collection, providers: Collection}
     */
    public function catalog(?string $locationId = null): array
    {
        $tenant = $this->tenantContext->get();

        $locations = Location::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'timezone', 'address', 'contact_phone', 'opening_hours']);

        $services = BookableService::query()
            ->where('is_active', true)
            ->where('is_bookable_online', true)
            ->orderBy('name')
            ->get();

        $providersQuery = TeamMember::query()
            ->where('is_active', true)
            ->whereHas('staffProfile', fn ($q) => $q->where('is_bookable', true))
            ->with(['staffProfile', 'operatingLocations:id,name'])
            ->orderBy('display_name');

        if ($locationId !== null) {
            $this->scope->findLocation($locationId);
            $providersQuery->where(function ($q) use ($locationId) {
                $q->where('primary_location_id', $locationId)
                    ->orWhereHas('operatingLocations', fn ($lq) => $lq->where('locations.id', $locationId))
                    ->orWhereDoesntHave('operatingLocations');
            });
        }

        return [
            'tenant' => [
                'id' => $tenant?->id,
                'name' => $tenant?->name,
                'slug' => $tenant?->slug,
                'branding' => $this->publicBranding($tenant?->getBranding() ?? []),
            ],
            'locations' => $locations,
            'services' => $services,
            'providers' => $providersQuery->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    private function publicBranding(array $branding): array
    {
        foreach (['logo_url', 'hero_emblem_url', 'hero_image_url'] as $key) {
            if (isset($branding[$key]) && is_string($branding[$key])) {
                $branding[$key] = PublicStorageUrl::normalize($branding[$key]);
            }
        }

        return $branding;
    }

    /**
     * @return array<int, array{starts_at: string, ends_at: string, team_member_id: string, location_id: string}>
     */
    public function availableSlots(
        string $bookingServiceId,
        string $locationId,
        string $date,
        ?string $teamMemberId = null,
    ): array {
        $service = $this->scope->findBookableService($bookingServiceId);
        if (! $service->is_active || ! $service->is_bookable_online) {
            throw ValidationException::withMessages([
                'booking_service_id' => ['Service is not available for online booking.'],
            ]);
        }

        $this->scope->findLocation($locationId);
        $location = Location::query()->findOrFail($locationId);
        $day = Carbon::parse($date)->startOfDay();
        if ($day->lt(Carbon::today())) {
            throw ValidationException::withMessages([
                'date' => ['Cannot book slots in the past.'],
            ]);
        }

        $storeWindows = $location->openingWindowsForDay($day->dayOfWeekIso);
        if ($storeWindows === []) {
            return [];
        }

        $duration = max(1, (int) $service->duration_minutes);
        $providers = $this->resolveProvidersForSlotSearch($locationId, $teamMemberId);
        $slots = [];

        foreach ($providers as $provider) {
            $rules = StaffAvailabilityRule::query()
                ->where('team_member_id', $provider->id)
                ->where('location_id', $locationId)
                ->where('day_of_week', $day->dayOfWeekIso)
                ->where('is_active', true)
                ->get();

            foreach ($rules as $rule) {
                $cursor = Carbon::parse($day->toDateString().' '.$rule->start_time);
                $windowEnd = Carbon::parse($day->toDateString().' '.$rule->end_time);

                while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                    $startsAt = $cursor->copy();
                    $endsAt = $cursor->copy()->addMinutes($duration);

                    if ($startsAt->gt(now())) {
                        try {
                            $this->schedulingValidator->validate(
                                $provider->id,
                                $locationId,
                                $rule->workspace_id,
                                $startsAt,
                                $endsAt,
                            );
                            $slots[] = [
                                'starts_at' => $startsAt->toIso8601String(),
                                'ends_at' => $endsAt->toIso8601String(),
                                'team_member_id' => $provider->id,
                                'location_id' => $locationId,
                                'workspace_id' => $rule->workspace_id,
                                'provider_name' => $provider->display_name,
                            ];
                        } catch (ValidationException) {
                            // Slot unavailable — skip.
                        }
                    }

                    $cursor->addMinutes($duration);
                }
            }
        }

        usort($slots, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));

        return array_slice($slots, 0, 48);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function book(array $payload): Appointment
    {
        $service = $this->scope->findBookableService($payload['booking_service_id']);
        if (! $service->is_active || ! $service->is_bookable_online) {
            throw ValidationException::withMessages([
                'booking_service_id' => ['Service is not available for online booking.'],
            ]);
        }

        $tier = $payload['pricing_tier'] ?? 'regular';
        if (! in_array($tier, ['regular', 'membership', 'loyalty'], true)) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Invalid pricing tier.'],
            ]);
        }

        $client = $this->resolveClient($payload);
        $priced = $this->resolveTierPrice($service, $tier, $payload['member_token'] ?? null, $client);
        $client = $priced['client'];

        return $this->appointments->create([
            'client_id' => $client->id,
            'team_member_id' => $payload['team_member_id'],
            'location_id' => $payload['location_id'],
            'workspace_id' => $payload['workspace_id'] ?? null,
            'starts_at' => $payload['starts_at'],
            'status' => Appointment::STATUS_CONFIRMED,
            'booking_source' => Appointment::SOURCE_ONLINE,
            'client_notes' => $payload['client_notes'] ?? null,
            'services' => [
                [
                    'booking_service_id' => $service->id,
                    'sort_order' => 0,
                    'price_cents' => $priced['price_cents'],
                    'pricing_tier' => $priced['pricing_tier'],
                ],
            ],
        ]);
    }

    /**
     * @return array{price_cents: int|null, pricing_tier: string, client: Client}
     */
    private function resolveTierPrice(
        BookableService $service,
        string $tier,
        ?string $memberToken,
        Client $client,
    ): array {
        if ($tier === 'regular') {
            return [
                'price_cents' => $service->base_price_cents,
                'pricing_tier' => 'regular',
                'client' => $client,
            ];
        }

        $memberClient = $this->memberPortal->findClientByToken($memberToken);

        if ($memberClient === null) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Please log in to the membership app to use '.$tier.' pricing.'],
            ]);
        }

        if (strtolower((string) $memberClient->email) === strtolower((string) $client->email)
            || $memberClient->id === $client->id) {
            $client = $memberClient;
        } else {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Booking details must match your membership login.'],
            ]);
        }

        $benefits = $this->memberPortal->benefitsFor($client);

        if ($tier === 'membership') {
            if (! $benefits['has_membership']) {
                throw ValidationException::withMessages([
                    'pricing_tier' => ['Membership pricing requires an active membership. Ask the salon to enrol you.'],
                ]);
            }
            if ($service->membership_price_cents === null) {
                throw ValidationException::withMessages([
                    'pricing_tier' => ['Membership pricing is not available for this service.'],
                ]);
            }

            return [
                'price_cents' => $service->membership_price_cents,
                'pricing_tier' => 'membership',
                'client' => $client,
            ];
        }

        if (! $benefits['loyalty_eligible']) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Loyalty pricing is not available for your account yet.'],
            ]);
        }
        if ($service->loyalty_price_cents === null) {
            throw ValidationException::withMessages([
                'pricing_tier' => ['Loyalty pricing is not available for this service.'],
            ]);
        }

        return [
            'price_cents' => $service->loyalty_price_cents,
            'pricing_tier' => 'loyalty',
            'client' => $client,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveClient(array $payload): Client
    {
        $email = strtolower(trim((string) $payload['email']));

        $existing = Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing !== null) {
            $updates = [];
            if (! empty($payload['phone']) && empty($existing->phone)) {
                $updates['phone'] = $payload['phone'];
            }
            if ($updates !== []) {
                $existing->fill($updates)->save();
            }

            return $existing->fresh();
        }

        return $this->clients->create([
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $email,
            'phone' => $payload['phone'] ?? null,
            'primary_location_id' => $payload['location_id'],
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, TeamMember>
     */
    private function resolveProvidersForSlotSearch(string $locationId, ?string $teamMemberId): Collection
    {
        if ($teamMemberId !== null) {
            $provider = $this->scope->findTeamMember($teamMemberId);
            $profile = StaffProfile::query()->where('team_member_id', $provider->id)->first();
            if (! $provider->is_active || $profile === null || ! $profile->is_bookable) {
                throw ValidationException::withMessages([
                    'team_member_id' => ['Provider is not bookable online.'],
                ]);
            }

            return collect([$provider]);
        }

        return TeamMember::query()
            ->where('is_active', true)
            ->whereHas('staffProfile', fn ($q) => $q->where('is_bookable', true))
            ->where(function ($q) use ($locationId) {
                $q->where('primary_location_id', $locationId)
                    ->orWhereHas('operatingLocations', fn ($lq) => $lq->where('locations.id', $locationId))
                    ->orWhereDoesntHave('operatingLocations');
            })
            ->get();
    }
}
